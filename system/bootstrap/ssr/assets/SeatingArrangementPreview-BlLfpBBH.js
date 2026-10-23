import { c as attr_class, e as escape_html, f as attr_style, b as attr, a as ensure_array_like, d as derived } from "../app.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function SeatingArrangementPreview($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      rows = 0,
      columns = 0,
      booklet_pattern = "checkerboard",
      desks = [],
      max_desks = 400,
      title = "Deterministic desk preview",
      compact = false
    } = $$props;
    function rowLabel(row) {
      let label = "";
      for (let value = row + 1; value > 0; value = Math.floor((value - 1) / 26)) {
        label = String.fromCharCode(65 + (value - 1) % 26) + label;
      }
      return label;
    }
    function bookletFor(index, row, column) {
      if (booklet_pattern === "uniform") return "A";
      if (booklet_pattern === "checkerboard") return (row + column) % 2 === 0 ? "A" : "B";
      if (booklet_pattern === "latin_four") return ["A", "B", "C", "D"][(row + column) % 4];
      return index % 2 === 0 ? "A" : "B";
    }
    const totalDesks = derived(() => Math.max(0, Number(rows || 0) * Number(columns || 0)));
    const previewDesks = derived(() => {
      if (Array.isArray(desks) && desks.length > 0) {
        return desks.slice(0, max_desks).map((desk, index) => ({
          index: desk.index ?? index,
          label: desk.label ?? `${rowLabel(Number(desk.row ?? 0))}${Number(desk.column ?? index) + 1}`,
          booklet: desk.booklet ?? desk.set_code ?? ""
        }));
      }
      const result = [];
      const limit = Math.min(totalDesks(), max_desks);
      const columnCount2 = Math.max(1, Number(columns || 0));
      for (let index = 0; index < limit; index += 1) {
        const row = Math.floor(index / columnCount2);
        const column = index % columnCount2;
        result.push({
          index,
          label: `${rowLabel(row)}${column + 1}`,
          booklet: bookletFor(index, row, column)
        });
      }
      return result;
    });
    const isLimited = derived(() => totalDesks() > previewDesks().length);
    const columnCount = derived(() => Math.max(1, Number(columns || 0)));
    $$renderer2.push(`<section${attr_class(`border-border bg-card rounded-xl border ${compact ? "p-4" : "p-5"}`)} aria-labelledby="seating-preview-title"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="seating-preview-title" class="font-semibold">${escape_html(title)}</h2> <p class="text-muted-foreground mt-1 text-sm">${escape_html(totalDesks())} ${escape_html(totalDesks() === 1 ? "desk" : "desks")} · ${escape_html(rows || 0)} rows × ${escape_html(columns || 0)} columns</p></div> `);
    if (totalDesks() > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="text-muted-foreground rounded-md border border-border px-2 py-1 text-xs">${escape_html(booklet_pattern === "uniform" ? "Uniform distribution" : booklet_pattern === "checkerboard" ? "Checkerboard distribution" : booklet_pattern === "latin_four" ? "Latin four distribution" : "Alternating distribution")}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (totalDesks() === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-border bg-muted/20 text-muted-foreground mt-4 rounded-lg border border-dashed p-6 text-center text-sm">Add rows and columns to preview the ordered desk manifest.</div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="border-border bg-muted/10 mt-4 max-h-[28rem] overflow-auto rounded-lg border p-3" tabindex="0" aria-label="Scrollable seating arrangement preview"><div class="grid min-w-max gap-2"${attr_style(`grid-template-columns: repeat(${columnCount()}, minmax(3rem, 1fr));`)} role="grid"${attr("aria-rowcount", rows)}${attr("aria-colcount", columns)}><!--[-->`);
      const each_array = ensure_array_like(previewDesks());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let desk = each_array[$$index];
        $$renderer2.push(`<div class="border-border bg-background flex min-h-12 min-w-12 flex-col items-center justify-center rounded-md border text-center shadow-sm" role="gridcell"${attr("aria-label", `${desk.label}${desk.booklet ? ` · distribution variant ${desk.booklet}` : ""}`)}><span class="font-mono text-xs font-semibold">${escape_html(desk.label)}</span> `);
        if (desk.booklet) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="text-muted-foreground mt-0.5 text-[10px]">${escape_html(desk.booklet)}</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div></div> `);
      if (isLimited()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-muted-foreground mt-2 text-xs">Preview shows the first ${escape_html(previewDesks().length)} desks. The saved manifest still includes all ${escape_html(totalDesks())} desks in row-major order.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
export {
  SeatingArrangementPreview as default
};
