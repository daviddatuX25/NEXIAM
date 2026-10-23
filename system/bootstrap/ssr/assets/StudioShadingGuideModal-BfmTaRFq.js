import { c as attr_class, e as escape_html, b as attr, l as bind_props, d as derived, a as ensure_array_like, f as attr_style, s as stringify } from "../app.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { S as Scan } from "./scan-DTyWR4bt.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { F as File_image } from "./file-image-BGlN21-8.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./Icon-Dal7PqKK.js";
function StudioShadingGuideModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      onOpenChange = () => {
      },
      challenge = null,
      method = null,
      onOpenPrintPdf = () => {
      }
    } = $$props;
    let activeScreen = 1;
    let currentPage = 1;
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isDragging = false;
    const guide = derived(() => challenge?.guide ?? null);
    const guideSections = derived(() => Array.isArray(guide()?.sections) ? guide().sections : []);
    const sheetPages = derived(() => Array.isArray(challenge?.sheet_pages) ? challenge.sheet_pages : []);
    const totalPages = derived(() => Math.max(1, ...sheetPages().map((page) => Number(page?.page_no) || 1), ...guideSections().map((section) => Number(section?.page_no) || 1)));
    const activeSheetPage = derived(() => sheetPages().find((p) => Number(p.page_no) === Number(currentPage)) || sheetPages()[0] || null);
    const activeGuideSections = derived(() => guideSections().filter((section) => Number(section?.page_no) === Number(currentPage)));
    const isHandShaded = derived(() => method === "hand_shaded" || guide()?.method === "hand_shaded");
    const sheetImageUrl = derived(() => activeSheetPage()?.url || (challenge?.print_mime?.startsWith?.("image/") ? challenge?.print_url : null) || null);
    const printPdfUrl = derived(() => challenge?.print_url || null);
    const guidePdfUrl = derived(() => challenge?.guide_pdf_url || challenge?.guide_url || null);
    const isFullQualification = derived(() => challenge?.mode === "full" || guide()?.mode === "full");
    const calibrationDisclaimer = derived(() => guide()?.calibration_disclaimer || "Calibration-ID disclaimer: this challenge shades deterministic calibration digits derived from the mapped identity grid (if one is mapped). They exist for THIS challenge only — they are not a real applicant identity and never enter grading.");
    function displayMarkLabel(label) {
      const value = String(label ?? "");
      const question = value.match(/(?:_Q_?|_)(\d+)$/);
      return question ? `Question ${Number(question[1])}` : value;
    }
    function resetZoom() {
      zoom = 1;
      panX = 0;
      panY = 0;
      isDragging = false;
    }
    function handleOpenChange(value) {
      open = value;
      if (!value) {
        activeScreen = 1;
        currentPage = 1;
        resetZoom();
      }
      onOpenChange(value);
    }
    Dialog($$renderer2, {
      open,
      onOpenChange: handleOpenChange,
      children: ($$renderer3) => {
        Dialog_content($$renderer3, {
          class: "text-foreground bg-background flex max-h-[92vh] w-full max-w-4xl flex-col p-6 sm:max-w-4xl",
          children: ($$renderer4) => {
            Dialog_header($$renderer4, {
              class: "shrink-0 space-y-2 pr-6",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
                Dialog_title($$renderer5, {
                  class: "text-foreground text-base font-bold sm:text-lg",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Physical tests`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> <span${attr_class(`rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase ${isFullQualification() ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300" : "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"}`)}>${escape_html(isFullQualification() ? "Full Challenge (Qualification)" : "Quick Rehearsal")}</span></div> `);
                Dialog_description($$renderer5, {
                  class: "text-muted-foreground text-xs leading-relaxed",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Review the marking guide, then copy the shaded reference.`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> <div class="bg-muted/40 border-border flex items-center gap-1 rounded-lg border p-1 text-xs" role="tablist" aria-label="Walkthrough steps"><button type="button" role="tab"${attr("aria-selected", activeScreen === 1)} aria-controls="guide-screen-1" id="tab-screen-1"${attr_class(`inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 font-semibold transition-all focus-visible:ring-2 focus-visible:outline-none ${activeScreen === 1 ? "bg-background text-foreground shadow-xs" : "text-muted-foreground hover:text-foreground hover:bg-background/50"}`)}><span${attr_class(`flex size-4.5 items-center justify-center rounded-full text-[10px] font-bold ${activeScreen === 1 ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900" : "bg-muted text-muted-foreground"}`)}>1</span> <span>Instructions &amp; Standards</span></button> <button type="button" role="tab"${attr("aria-selected", activeScreen === 2)} aria-controls="guide-screen-2" id="tab-screen-2"${attr_class(`inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 font-semibold transition-all focus-visible:ring-2 focus-visible:outline-none ${activeScreen === 2 ? "bg-background text-foreground shadow-xs" : "text-muted-foreground hover:text-foreground hover:bg-background/50"}`)}><span${attr_class(`flex size-4.5 items-center justify-center rounded-full text-[10px] font-bold ${activeScreen === 2 ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900" : "bg-muted text-muted-foreground"}`)}>2</span> <span>${escape_html(isHandShaded() ? "Marks to Shade" : "Challenge Sheet & Marks")}</span></button></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (activeScreen === 1) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div id="guide-screen-1" role="tabpanel" aria-labelledby="tab-screen-1" class="-mr-1 space-y-5 overflow-y-auto py-2 pr-1 text-xs"><div class="space-y-2.5"><div class="flex items-center justify-between"><h3 class="text-foreground flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase">`);
              Sparkles($$renderer4, { class: "size-3.5 text-blue-600 dark:text-blue-400" });
              $$renderer4.push(`<!----> <span>Optical Mark Shading Standards</span></h3> <span class="text-muted-foreground text-[11px]">High-contrast optical sensor rules</span></div> <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="border-border/80 bg-card flex flex-col items-center space-y-2 rounded-xl border p-3.5 text-center"><div class="flex h-14 items-center justify-center"><svg viewBox="0 0 60 60" class="size-12" aria-hidden="true"><circle cx="30" cy="30" r="20" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400 dark:text-slate-500"></circle><text x="30" y="36" text-anchor="middle" font-size="16" font-weight="bold" fill="currentColor" class="text-slate-600 dark:text-slate-400">A</text></svg></div> <div class="w-full space-y-1"><span class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">Unmarked</span> <p class="text-muted-foreground text-[11px] leading-tight">Clean bubble with clear interior</p></div></div> <div class="flex flex-col items-center space-y-2 rounded-xl border border-emerald-300 bg-emerald-50/40 p-3.5 text-center dark:border-emerald-800/80 dark:bg-emerald-950/20"><div class="flex h-14 items-center justify-center"><svg viewBox="0 0 60 60" class="size-12" aria-hidden="true"><circle cx="30" cy="30" r="20" class="fill-slate-900 stroke-slate-900 dark:fill-slate-100 dark:stroke-slate-100" stroke-width="2"></circle><text x="30" y="36" text-anchor="middle" font-size="16" font-weight="bold" class="fill-white dark:fill-slate-900">A</text></svg></div> <div class="w-full space-y-1"><span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">`);
              Check($$renderer4, { class: "size-3 stroke-[3]" });
              $$renderer4.push(`<!----> <span>Correct Fill</span></span> <p class="text-muted-foreground text-[11px] leading-tight">100% solid, dark, complete coverage</p></div></div> <div class="flex flex-col items-center space-y-2 rounded-xl border border-red-200 bg-red-50/40 p-3.5 text-center dark:border-red-900/60 dark:bg-red-950/20"><div class="flex h-14 items-center justify-center gap-1.5"><svg viewBox="0 0 36 36" class="size-7" aria-label="Tick mark" aria-hidden="true"><circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" stroke-width="1.5" class="text-slate-400"></circle><path d="M10 18l5 5 11-11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-red-500"></path></svg> <svg viewBox="0 0 36 36" class="size-7" aria-label="Cross mark" aria-hidden="true"><circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" stroke-width="1.5" class="text-slate-400"></circle><path d="M11 11l14 14M25 11l-14 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-red-500"></path></svg> <svg viewBox="0 0 36 36" class="size-7" aria-label="Dot mark" aria-hidden="true"><circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" stroke-width="1.5" class="text-slate-400"></circle><circle cx="18" cy="18" r="3.5" class="fill-slate-800 dark:fill-slate-200"></circle></svg></div> <div class="w-full space-y-1"><span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800 dark:bg-red-900/60 dark:text-red-300">`);
              Circle_x($$renderer4, { class: "size-3" });
              $$renderer4.push(`<!----> <span>Invalid Marks</span></span> <p class="text-muted-foreground text-[11px] leading-tight">No ticks, crosses, dots, or partial fills</p></div></div> <div class="flex flex-col items-center space-y-2 rounded-xl border border-amber-200 bg-amber-50/40 p-3.5 text-center dark:border-amber-900/60 dark:bg-amber-950/20"><div class="flex h-14 items-center justify-center"><svg viewBox="0 0 60 60" class="size-12" aria-hidden="true"><circle cx="30" cy="30" r="20" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400 dark:text-slate-500"></circle><circle cx="30" cy="30" r="18" class="fill-slate-400/15 dark:fill-slate-600/15"></circle><text x="30" y="36" text-anchor="middle" font-size="16" font-weight="bold" fill="currentColor" class="text-slate-400 opacity-50 dark:text-slate-500">A</text><path d="M16 44l28-28" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 2" class="text-amber-600/60 dark:text-amber-400/60"></path></svg></div> <div class="w-full space-y-1"><span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">`);
              Triangle_alert($$renderer4, { class: "size-3" });
              $$renderer4.push(`<!----> <span>Clean Erasure</span></span> <p class="text-muted-foreground text-[11px] leading-tight">Erase completely; avoid dark smudges</p></div></div></div></div> <div class="space-y-2.5"><h3 class="text-foreground text-xs font-bold tracking-wider uppercase">Physical Marking &amp; Print Rules</h3> <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"><div class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3">`);
              Circle_check($$renderer4, {
                class: "mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
              });
              $$renderer4.push(`<!----> <div class="space-y-0.5"><h4 class="text-foreground text-xs font-bold">#2 (HB) Pencil or Dark Black Pen</h4> <p class="text-muted-foreground text-[11px] leading-relaxed">Use a dark graphite pencil (#2 / HB) or
                                    solid black ballpoint pen. Fill bubbles
                                    completely to achieve full optical density.</p></div></div> <div class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3">`);
              Qr_code($$renderer4, {
                class: "mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400"
              });
              $$renderer4.push(`<!----> <div class="space-y-0.5"><h4 class="text-foreground text-xs font-bold">Pristine QR Code Area</h4> <p class="text-muted-foreground text-[11px] leading-relaxed">Do not fold, crease, smudge, or mark
                                    anywhere across the top corner QR code. The
                                    engine reads the QR to locate the exact
                                    plan.</p></div></div> <div class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3">`);
              Scan($$renderer4, {
                class: "mt-0.5 size-4 shrink-0 text-purple-600 dark:text-purple-400"
              });
              $$renderer4.push(`<!----> <div class="space-y-0.5"><h4 class="text-foreground text-xs font-bold">Corner Alignment Targets</h4> <p class="text-muted-foreground text-[11px] leading-relaxed">Keep the four black corner finder targets
                                    and edge timing tracks clean. Never staple,
                                    punch holes, or draw lines across them.</p></div></div> <div class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3">`);
              Printer($$renderer4, {
                class: "mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
              });
              $$renderer4.push(`<!----> <div class="space-y-0.5"><h4 class="text-foreground text-xs font-bold">Print at 100% (Actual Size)</h4> <p class="text-muted-foreground text-[11px] leading-relaxed">In your system print dialog, always choose <strong>Actual Size</strong> (100% scaling). Do not use “Fit to printable
                                    area” or page scaling.</p></div></div></div></div> <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/50 p-3.5 text-xs leading-relaxed text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200">`);
              Shield_alert($$renderer4, {
                class: "mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400"
              });
              $$renderer4.push(`<!----> <div class="space-y-0.5"><strong class="block font-semibold text-amber-950 dark:text-amber-100">Calibration-ID disclaimer</strong> <span class="text-[11px]">${escape_html(calibrationDisclaimer())}</span></div></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div id="guide-screen-2" role="tabpanel" aria-labelledby="tab-screen-2" class="-mr-1 space-y-3 overflow-y-auto py-2 pr-1 text-xs"><div class="space-y-0.5"><h3 class="text-foreground text-sm font-bold">${escape_html(isHandShaded() ? "Shade these exact bubbles" : "Review the printed pattern")}</h3> <p class="text-muted-foreground text-xs">${escape_html(isHandShaded() ? "On the blank printed sheet, shade only the bubbles listed below. Leave every other answer bubble blank." : "Use this on-screen reference to check the generated marks before printing.")}</p></div> `);
              if (isHandShaded()) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="border-border bg-card flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5"><span class="text-muted-foreground px-2 text-[11px] font-medium">Page ${escape_html(currentPage)} of ${escape_html(totalPages())}</span> `);
                if (totalPages() > 1) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="border-border bg-muted/30 inline-flex items-center rounded-md border p-0.5"><button type="button"${attr("disabled", currentPage <= 1, true)} aria-label="Previous guide page" class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30">`);
                  Chevron_left($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----></button> <!--[-->`);
                  const each_array = ensure_array_like(Array.from({ length: totalPages() }, (_, index) => index + 1));
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let page = each_array[$$index];
                    $$renderer4.push(`<button type="button"${attr("aria-current", currentPage === page ? "page" : void 0)}${attr("aria-label", `Guide page ${page}`)}${attr_class(`size-6 rounded text-[10px] font-bold transition ${currentPage === page ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900" : "text-muted-foreground hover:bg-background"}`)}>${escape_html(page)}</button>`);
                  }
                  $$renderer4.push(`<!--]--> <button type="button"${attr("disabled", currentPage >= totalPages(), true)} aria-label="Next guide page" class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30">`);
                  Chevron_right($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----></button></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> `);
                if (activeGuideSections().length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="space-y-3"${attr("aria-label", `Marks to shade on page ${currentPage}`)}><!--[-->`);
                  const each_array_1 = ensure_array_like(activeGuideSections());
                  for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
                    let section = each_array_1[$$index_2];
                    $$renderer4.push(`<section class="border-border/80 bg-card space-y-2 rounded-xl border p-3.5"${attr("aria-label", `${section.area} marks`)}><div class="flex flex-wrap items-baseline justify-between gap-1"><h4 class="text-foreground text-xs font-bold">${escape_html(section.area)}</h4> <span class="text-muted-foreground text-[10px]">${escape_html(section.labels?.length ?? 0)} bubbles</span></div> <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
                    const each_array_2 = ensure_array_like(section.labels ?? []);
                    for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                      let mark = each_array_2[$$index_1];
                      $$renderer4.push(`<div class="border-border bg-background flex min-w-0 items-center justify-between gap-3 rounded-lg border px-3 py-2"><span class="text-foreground min-w-0 truncate text-xs font-semibold">${escape_html(displayMarkLabel(mark.label))}</span> <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white dark:bg-slate-100 dark:text-slate-900"${attr("aria-label", `Shade option ${mark.option}`)}>${escape_html(mark.option)}</span></div>`);
                    }
                    $$renderer4.push(`<!--]--></div></section>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="border-border bg-muted/30 rounded-xl border p-5 text-center" role="status"><p class="text-foreground text-xs font-semibold">Marking pattern unavailable</p> <p class="text-muted-foreground mt-1 text-[11px]">This saved challenge has no structured mark
                                list. Regenerate the challenge before hand
                                shading.</p></div>`);
                }
                $$renderer4.push(`<!--]--> `);
                if (currentPage === 1 && Array.isArray(guide()?.identity_columns) && guide().identity_columns.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<section class="border-border bg-muted/30 space-y-2 rounded-xl border p-3.5" aria-label="Calibration digits to shade"><div><h4 class="text-foreground text-xs font-bold">Applicant number calibration</h4> <p class="text-muted-foreground mt-0.5 text-[11px]">Shade one digit in each listed column. These
                                    digits identify only this calibration
                                    challenge.</p></div> <div class="flex flex-wrap gap-2"><!--[-->`);
                  const each_array_3 = ensure_array_like(guide().identity_columns);
                  for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                    let column = each_array_3[$$index_3];
                    $$renderer4.push(`<div class="border-border bg-background inline-flex items-center gap-2 rounded-lg border px-3 py-2"><span class="text-muted-foreground text-[11px] font-medium">${escape_html(column.label)}</span> <span class="flex size-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900">${escape_html(column.digit)}</span></div>`);
                  }
                  $$renderer4.push(`<!--]--></div></section>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]-->`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<div class="border-border bg-card flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5 text-xs shadow-2xs"><div class="flex items-center gap-1.5">`);
                if (totalPages() > 1) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="border-border bg-muted/30 inline-flex items-center rounded-md border p-0.5"><button type="button"${attr("disabled", currentPage <= 1, true)} aria-label="Previous sheet page" class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30">`);
                  Chevron_left($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----></button> <div class="flex items-center gap-0.5 px-1"><!--[-->`);
                  const each_array_4 = ensure_array_like(Array.from({ length: totalPages() }, (_, i) => i + 1));
                  for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                    let p = each_array_4[$$index_4];
                    $$renderer4.push(`<button type="button"${attr("aria-current", currentPage === p ? "page" : void 0)}${attr_class(`size-5 rounded text-[10px] font-bold transition ${currentPage === p ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900" : "text-muted-foreground hover:bg-background"}`)}>${escape_html(p)}</button>`);
                  }
                  $$renderer4.push(`<!--]--></div> <button type="button"${attr("disabled", currentPage >= totalPages(), true)} aria-label="Next sheet page" class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30">`);
                  Chevron_right($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----></button></div> <span class="text-muted-foreground text-[11px] font-medium">Page ${escape_html(currentPage)} of ${escape_html(totalPages())}</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <div class="flex items-center gap-1"><button type="button"${attr("disabled", zoom <= 0.5, true)} aria-label="Zoom out" title="Zoom out (-)" class="border-border bg-background hover:bg-muted rounded-md border p-1.5 transition disabled:pointer-events-none disabled:opacity-30">`);
                Minus($$renderer4, { class: "text-muted-foreground size-3.5" });
                $$renderer4.push(`<!----></button> <span class="text-foreground min-w-[3.2rem] px-2 text-center font-mono text-xs font-bold" aria-label="Current zoom level">${escape_html(Math.round(zoom * 100))}%</span> <button type="button"${attr("disabled", zoom >= 3, true)} aria-label="Zoom in" title="Zoom in (+)" class="border-border bg-background hover:bg-muted rounded-md border p-1.5 transition disabled:pointer-events-none disabled:opacity-30">`);
                Plus($$renderer4, { class: "text-muted-foreground size-3.5" });
                $$renderer4.push(`<!----></button> <button type="button" aria-label="Fit to view" title="Fit to view (0)" class="border-border bg-background hover:bg-muted text-foreground ml-1 inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold transition">`);
                Rotate_ccw($$renderer4, { class: "text-muted-foreground size-3" });
                $$renderer4.push(`<!----> <span>Fit</span></button></div></div> `);
                if (sheetImageUrl()) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div role="region" tabindex="0" aria-label="Zoomable challenge sheet viewer"${attr_class(`border-border relative flex h-[400px] w-full touch-none items-center justify-center overflow-hidden rounded-xl border bg-slate-950/5 select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none sm:h-[480px] dark:bg-slate-900/40 ${zoom > 1 ? isDragging ? "cursor-grabbing" : "cursor-grab" : "cursor-default"}`)}><div class="pointer-events-none inline-flex origin-center items-center justify-center transition-transform duration-75 ease-out"${attr_style(`transform: translate(${stringify(panX)}px, ${stringify(panY)}px) scale(${stringify(zoom)});`)}><img${attr("src", sheetImageUrl())}${attr("alt", `Challenge sheet page ${stringify(currentPage)}`)} class="border-border/80 max-h-[380px] w-auto max-w-full rounded-lg border bg-white object-contain shadow-sm select-none sm:max-h-[460px]" draggable="false"/></div></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="border-border flex min-h-[350px] flex-col items-center justify-center space-y-2.5 rounded-xl border bg-slate-950/5 p-8 text-center dark:bg-slate-900/40">`);
                  File_image($$renderer4, { class: "text-muted-foreground/60 mx-auto size-8" });
                  $$renderer4.push(`<!----> <div class="text-foreground text-xs font-semibold">Challenge Sheet Available</div> <p class="text-muted-foreground max-w-sm text-[11px] leading-relaxed">Physical sheet preview is ready. You can
                                download or print the PDF directly for physical
                                verification.</p> `);
                  if (printPdfUrl()) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition">`);
                    Printer($$renderer4, { class: "size-3.5" });
                    $$renderer4.push(`<!----> <span>Open sheet PDF</span></button>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--></div>`);
                }
                $$renderer4.push(`<!--]--> <div class="text-muted-foreground flex flex-wrap items-center justify-between gap-1 px-1 text-[10px]"><span>Mouse wheel to zoom • Drag to pan •
                            Double click to toggle 150%</span> <span>Keyboard: + / - / 0, Arrow keys to pan</span></div>`);
              }
              $$renderer4.push(`<!--]--></div>`);
            }
            $$renderer4.push(`<!--]--> `);
            Dialog_footer($$renderer4, {
              class: "border-border flex shrink-0 flex-col-reverse gap-2.5 border-t pt-3 sm:flex-row sm:items-center sm:justify-between",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
                if (activeScreen === 2 && guidePdfUrl()) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
                  File_image($$renderer5, { class: "text-muted-foreground size-3.5" });
                  $$renderer5.push(`<!----> <span>Open guide PDF (optional)</span></button>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> `);
                if (activeScreen === 2 && printPdfUrl()) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
                  Printer($$renderer5, { class: "text-muted-foreground size-3.5" });
                  $$renderer5.push(`<!----> <span>${escape_html(isHandShaded() ? "Print blank sheet (optional)" : "Print sheet (optional)")}</span></button>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div> <div class="flex items-center gap-2">`);
                if (activeScreen === 2) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1 rounded-lg border px-3.5 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
                  Arrow_left($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> <span>Back to Instructions</span></button>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> <button type="button" class="border-border bg-card hover:bg-muted text-foreground rounded-lg border px-3.5 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">Close</button> `);
                if (activeScreen === 1) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"><span>${escape_html(isHandShaded() ? "Next: View Marks to Shade" : "Next: View Challenge Sheet")}</span> `);
                  Arrow_right($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----></button>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">`);
                  Check($$renderer5, { class: "size-3.5 stroke-[2.5]" });
                  $$renderer5.push(`<!----> <span>Done — return to test</span></button>`);
                }
                $$renderer5.push(`<!--]--></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
      },
      $$slots: { default: true }
    });
    bind_props($$props, { open });
  });
}
export {
  StudioShadingGuideModal as default
};
