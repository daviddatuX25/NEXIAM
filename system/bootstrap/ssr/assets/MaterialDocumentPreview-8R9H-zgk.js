import { c as attr_class, b as attr, a as ensure_array_like, e as escape_html, d as derived } from "../app.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
function MaterialDocumentPreview($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      src = null,
      alt = "",
      kind = "answer_sheet",
      title = "",
      page_count = 0,
      sets = [],
      mode = null,
      builder_json = null,
      orientation = "portrait",
      overlay = null
    } = $$props;
    const previewRows = [0, 1, 2, 3, 4];
    const previewBubbles = [0, 1, 2, 3];
    const visibleSets = derived(() => sets.slice(0, 4));
    const previewBlocks = derived(() => Array.isArray(builder_json?.components) ? builder_json.components.filter((component) => [
      "header",
      "kpi_grid",
      "data_table",
      "score_table",
      "identity",
      "recommendation",
      "signature",
      "footer",
      "divider",
      "text"
    ].includes(component?.type)).slice(0, 5).map((component) => component.type) : []);
    const accessibleLabel = derived(() => alt || (kind === "answer_keys_only" ? `${title} external Booklet; question content is not stored` : `${title} document preview`));
    $$renderer2.push(`<div${attr_class(`border-border bg-muted/35 relative shrink-0 overflow-hidden rounded-lg border shadow-[0_8px_20px_-16px_rgba(15,23,42,0.45)] ${orientation === "landscape" ? "aspect-[1.414] w-28 sm:w-32" : "aspect-[0.707] w-24 sm:w-28"}`)} role="img"${attr("aria-label", accessibleLabel())}>`);
    if (src && true) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<img${attr("src", src)}${attr("alt", alt)} class="bg-background h-full w-full object-contain" loading="lazy" onerror="this.__e=event"/>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="bg-background absolute inset-1.5 flex flex-col overflow-hidden rounded-[5px] border p-2 sm:inset-2">`);
      if (previewBlocks().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-hidden"><!--[-->`);
        const each_array = ensure_array_like(previewBlocks());
        for (let index = 0, $$length = each_array.length; index < $$length; index++) {
          let block = each_array[index];
          if (block === "header") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="space-y-1"><div class="bg-primary/35 h-2 w-full rounded-xs"></div> <div class="bg-muted-foreground/25 h-1 w-3/4 rounded-full"></div></div>`);
          } else if (block === "kpi_grid") {
            $$renderer2.push("<!--[1-->");
            $$renderer2.push(`<div class="grid grid-cols-3 gap-1"><!--[-->`);
            const each_array_1 = ensure_array_like([0, 1, 2]);
            for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
              each_array_1[$$index];
              $$renderer2.push(`<div class="border-muted-foreground/20 bg-muted/40 rounded-[3px] border p-1"><div class="bg-muted-foreground/25 h-0.5 w-3/4 rounded-full"></div> <div class="bg-primary/45 mt-1 h-1.5 rounded-xs"></div></div>`);
            }
            $$renderer2.push(`<!--]--></div>`);
          } else if (block === "data_table" || block === "score_table") {
            $$renderer2.push("<!--[2-->");
            $$renderer2.push(`<div class="border-muted-foreground/25 overflow-hidden rounded-[3px] border"><div class="bg-muted-foreground/15 grid grid-cols-3 gap-1 p-1"><!--[-->`);
            const each_array_2 = ensure_array_like([0, 1, 2]);
            for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
              each_array_2[$$index_1];
              $$renderer2.push(`<div class="bg-muted-foreground/35 h-1 rounded-full"></div>`);
            }
            $$renderer2.push(`<!--]--></div> <!--[-->`);
            const each_array_3 = ensure_array_like([0, 1, 2]);
            for (let $$index_3 = 0, $$length2 = each_array_3.length; $$index_3 < $$length2; $$index_3++) {
              each_array_3[$$index_3];
              $$renderer2.push(`<div class="grid grid-cols-3 gap-1 border-t border-muted-foreground/15 px-1 py-1"><!--[-->`);
              const each_array_4 = ensure_array_like([0, 1, 2]);
              for (let $$index_2 = 0, $$length3 = each_array_4.length; $$index_2 < $$length3; $$index_2++) {
                each_array_4[$$index_2];
                $$renderer2.push(`<div class="bg-muted-foreground/20 h-0.5 rounded-full"></div>`);
              }
              $$renderer2.push(`<!--]--></div>`);
            }
            $$renderer2.push(`<!--]--></div>`);
          } else if (block === "identity" || block === "recommendation") {
            $$renderer2.push("<!--[3-->");
            $$renderer2.push(`<div class="border-muted-foreground/20 bg-muted/25 rounded-[3px] border p-1.5"><div class="bg-muted-foreground/35 h-1 w-2/5 rounded-full"></div> <div class="bg-muted-foreground/20 mt-1.5 h-1 w-full rounded-full"></div> <div class="bg-muted-foreground/20 mt-1 h-1 w-4/5 rounded-full"></div></div>`);
          } else if (block === "signature") {
            $$renderer2.push("<!--[4-->");
            $$renderer2.push(`<div class="mt-auto flex flex-col items-center px-2 pt-1"><div class="border-muted-foreground/40 w-3/4 border-t"></div> <div class="bg-muted-foreground/25 mt-1 h-0.5 w-2/5 rounded-full"></div></div>`);
          } else if (block === "footer") {
            $$renderer2.push("<!--[5-->");
            $$renderer2.push(`<div class="bg-muted-foreground/20 mt-auto h-0.5 w-3/5 rounded-full"></div>`);
          } else if (block === "divider") {
            $$renderer2.push("<!--[6-->");
            $$renderer2.push(`<div class="border-muted-foreground/25 border-t"></div>`);
          } else if (block === "text") {
            $$renderer2.push("<!--[7-->");
            $$renderer2.push(`<div class="space-y-1"><div class="bg-muted-foreground/25 h-1 w-full rounded-full"></div> <div class="bg-muted-foreground/25 h-1 w-4/5 rounded-full"></div></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        }
        $$renderer2.push(`<!--]--></div>`);
      } else if (kind === "answer_sheet") {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div> <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div> <div class="mt-3 grid grid-cols-[1fr_auto] gap-x-1.5 gap-y-1.5"><!--[-->`);
        const each_array_5 = ensure_array_like(previewRows);
        for (let $$index_6 = 0, $$length = each_array_5.length; $$index_6 < $$length; $$index_6++) {
          each_array_5[$$index_6];
          $$renderer2.push(`<div class="bg-muted h-1 rounded-full"></div> <div class="flex gap-0.5"><!--[-->`);
          const each_array_6 = ensure_array_like(previewBubbles);
          for (let $$index_5 = 0, $$length2 = each_array_6.length; $$index_5 < $$length2; $$index_5++) {
            each_array_6[$$index_5];
            $$renderer2.push(`<span class="border-muted-foreground/45 size-1.5 rounded-full border"></span>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></div> <div class="border-muted-foreground/20 mt-auto border-t pt-1.5"><div class="bg-muted h-1 w-4/5 rounded-full"></div></div>`);
      } else if (kind === "stored_questions") {
        $$renderer2.push("<!--[2-->");
        $$renderer2.push(`<div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div> <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div> <p class="text-foreground mt-3 line-clamp-2 text-[7px] leading-tight font-semibold uppercase sm:text-[8px]">${escape_html(title)}</p> <div class="mt-2 space-y-1.5"><!--[-->`);
        const each_array_7 = ensure_array_like(previewBubbles);
        for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
          each_array_7[$$index_7];
          $$renderer2.push(`<div class="space-y-0.5"><div class="bg-muted-foreground/30 h-1 rounded-full"></div> <div class="bg-muted h-1 w-4/5 rounded-full"></div></div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      } else if (kind === "report") {
        $$renderer2.push("<!--[3-->");
        $$renderer2.push(`<div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div> <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div> <div class="mt-3 grid grid-cols-3 gap-1"><!--[-->`);
        const each_array_8 = ensure_array_like([0, 1, 2]);
        for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
          each_array_8[$$index_8];
          $$renderer2.push(`<div class="border-muted-foreground/20 bg-muted/40 rounded-[3px] border p-1"><div class="bg-muted-foreground/25 h-0.5 w-3/4 rounded-full"></div> <div class="bg-primary/45 mt-1 h-1.5 rounded-xs"></div></div>`);
        }
        $$renderer2.push(`<!--]--></div> <div class="border-muted-foreground/25 mt-2 overflow-hidden rounded-[3px] border"><div class="bg-muted-foreground/15 grid grid-cols-3 gap-1 p-1"><!--[-->`);
        const each_array_9 = ensure_array_like([0, 1, 2]);
        for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
          each_array_9[$$index_9];
          $$renderer2.push(`<div class="bg-muted-foreground/35 h-1 rounded-full"></div>`);
        }
        $$renderer2.push(`<!--]--></div> <!--[-->`);
        const each_array_10 = ensure_array_like([0, 1, 2]);
        for (let $$index_11 = 0, $$length = each_array_10.length; $$index_11 < $$length; $$index_11++) {
          each_array_10[$$index_11];
          $$renderer2.push(`<div class="grid grid-cols-3 gap-1 border-t border-muted-foreground/15 px-1 py-1"><!--[-->`);
          const each_array_11 = ensure_array_like([0, 1, 2]);
          for (let $$index_10 = 0, $$length2 = each_array_11.length; $$index_10 < $$length2; $$index_10++) {
            each_array_11[$$index_10];
            $$renderer2.push(`<div class="bg-muted-foreground/20 h-0.5 rounded-full"></div>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></div> <div class="border-muted-foreground/20 mt-auto flex justify-center border-t pt-1.5"><div class="border-muted-foreground/40 w-2/3 border-t pt-1"><div class="bg-muted-foreground/25 mx-auto h-0.5 w-2/5 rounded-full"></div></div></div>`);
      } else if (kind === "result_sheet") {
        $$renderer2.push("<!--[4-->");
        $$renderer2.push(`<div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div> <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div> <div class="space-y-1"><div class="bg-primary/25 h-1.5 w-full rounded-xs"></div> <div class="bg-muted-foreground/30 h-1 w-3/4 rounded-full"></div></div> <div class="border-muted-foreground/20 bg-muted/25 mt-2 rounded-[3px] border p-1 space-y-0.5"><div class="bg-muted-foreground/40 h-1 w-1/2 rounded-full"></div> <div class="bg-foreground/50 h-1 w-4/5 rounded-full"></div></div> <div class="mt-2 space-y-1"><!--[-->`);
        const each_array_12 = ensure_array_like([0, 1, 2]);
        for (let $$index_12 = 0, $$length = each_array_12.length; $$index_12 < $$length; $$index_12++) {
          each_array_12[$$index_12];
          $$renderer2.push(`<div class="grid grid-cols-[1fr_auto] items-center gap-1"><div class="bg-muted-foreground/25 h-1 rounded-full"></div> <div class="bg-primary/50 h-1 w-3 rounded-full"></div></div>`);
        }
        $$renderer2.push(`<!--]--></div> <div class="border-muted-foreground/20 mt-auto flex items-center justify-between border-t pt-1.5"><div class="bg-emerald-500/40 h-1.5 w-2/5 rounded-xs"></div> <div class="bg-muted-foreground/30 h-0.5 w-1/4 rounded-full"></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div> <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div> <div class="my-auto text-center"><p class="text-muted-foreground text-[7px] leading-tight font-semibold tracking-wide uppercase sm:text-[8px]">Answer Key Only</p> <p class="text-muted-foreground/75 mt-1 text-[6px] leading-tight sm:text-[7px]">Questions external</p></div>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (overlay === "booklet" || (kind === "stored_questions" || kind === "answer_keys_only") && src && true) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute top-1.5 left-1.5 flex items-center gap-1 rounded bg-slate-900/85 px-1.5 py-0.5 text-white shadow-xs backdrop-blur-xs border border-white/20" title="Question Booklet (Paired Answer Sheet)" aria-label="Question Booklet">`);
      Book_open($$renderer2, { class: "size-3 text-amber-300" });
      $$renderer2.push(`<!----> <span class="text-[8px] font-semibold tracking-wide uppercase">${escape_html(kind === "answer_keys_only" ? "Booklet · Key" : "Booklet")}</span></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (page_count > 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="bg-foreground text-background absolute right-1.5 bottom-1.5 rounded px-1.5 py-0.5 text-[9px] font-semibold">+${escape_html(page_count - 1)}</span>`);
    } else if (visibleSets().length > 0) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="absolute right-1.5 bottom-1.5 flex gap-0.5" aria-hidden="true"><!--[-->`);
      const each_array_13 = ensure_array_like(visibleSets());
      for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
        let set = each_array_13[$$index_13];
        $$renderer2.push(`<span${attr_class("bg-foreground text-background flex size-4 items-center justify-center rounded text-[8px] font-semibold", void 0, { "opacity-55": !set.is_complete })}>${escape_html(set.code)}</span>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else if (mode) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<span class="bg-foreground text-background absolute right-1.5 bottom-1.5 rounded px-1.5 py-0.5 font-mono text-[8px] font-semibold tracking-wider uppercase">${escape_html(mode)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  MaterialDocumentPreview as M
};
