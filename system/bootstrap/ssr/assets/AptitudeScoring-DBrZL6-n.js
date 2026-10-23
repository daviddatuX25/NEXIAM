import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, B as Button, a as ensure_array_like, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { M as ModeBanner } from "./ModeBanner-CuWMSWxe.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
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
function Clipboard_paste($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M11 14h10" }],
    ["path", { "d": "M16 4h2a2 2 0 0 1 2 2v1.344" }],
    ["path", { "d": "m17 18 4-4-4-4" }],
    [
      "path",
      {
        "d": "M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 1.793-1.113"
      }
    ],
    [
      "rect",
      { "x": "8", "y": "2", "width": "8", "height": "4", "rx": "1" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "clipboard-paste" },
    $$sanitized_props,
    {
      /**
       * @component @name ClipboardPaste
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTEgMTRoMTAiIC8+CiAgPHBhdGggZD0iTTE2IDRoMmEyIDIgMCAwIDEgMiAydjEuMzQ0IiAvPgogIDxwYXRoIGQ9Im0xNyAxOCA0LTQtNC00IiAvPgogIDxwYXRoIGQ9Ik04IDRINmEyIDIgMCAwIDAtMiAydjE0YTIgMiAwIDAgMCAyIDJoMTJhMiAyIDAgMCAwIDEuNzkzLTEuMTEzIiAvPgogIDxyZWN0IHg9IjgiIHk9IjIiIHdpZHRoPSI4IiBoZWlnaHQ9IjQiIHJ4PSIxIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/clipboard-paste
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function AptitudeScoring($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { area = {}, conversion_rows = [] } = $$props;
    const page = usePage();
    const saveError = derived(() => page.props.errors?.scoring ?? "");
    const status = derived(() => page.props.flash?.status ?? "");
    let maxItems = area.max_items ?? 100;
    let rows = conversion_rows.map((r) => ({
      min_score: r.min_score,
      max_score: r.max_score,
      output_value: r.output_value
    }));
    let pasteOpen = false;
    let pasteText = "";
    let pasteError = "";
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Aptitude areas", href: "/setup/aptitude" },
      { label: `${area.code ?? ""} scoring` }
    ];
    function addRow() {
      const nextMin = rows.length === 0 ? 0 : Math.max(rows[rows.length - 1].max_score + 1, 0);
      rows = [
        ...rows,
        { min_score: nextMin, max_score: nextMin, output_value: "" }
      ];
    }
    function removeRow(index) {
      rows = rows.filter((_, i) => i !== index);
    }
    function collapseTable(flatRows) {
      const sorted = [...flatRows].filter((r) => r.min_score !== "" && r.min_score !== null).sort((a, b) => Number(a.min_score) - Number(b.min_score));
      const uiRows = [];
      let currentGroup = null;
      for (const row of sorted) {
        const start = Number(row.min_score);
        const end = Number(row.max_score);
        const out = row.output_value;
        if (!currentGroup) {
          currentGroup = { start, end, percentile: out };
          continue;
        }
        if (start === currentGroup.end + 1 && out === currentGroup.percentile) {
          currentGroup.end = end;
        } else {
          uiRows.push(currentGroup);
          currentGroup = { start, end, percentile: out };
        }
      }
      if (currentGroup) uiRows.push(currentGroup);
      return uiRows;
    }
    function applyPaste() {
      pasteError = "";
      if (!pasteText.trim()) {
        pasteError = "Paste area is empty.";
        return;
      }
      const lines = pasteText.trim().split(/\r?\n/).filter((l) => l.trim());
      const parsed = [];
      for (const line of lines) {
        const separatorIndex = line.search(/[\t:=]|\s{2,}/);
        let scorePart = "";
        let out = "";
        if (separatorIndex !== -1) {
          scorePart = line.substring(0, separatorIndex).trim();
          const matchedDelim = line.match(/[\t:=]|\s{2,}/);
          const delimLength = matchedDelim ? matchedDelim[0].length : 1;
          out = line.substring(separatorIndex + delimLength).trim();
        } else {
          const spaceIndex = line.indexOf(" ");
          if (spaceIndex === -1) continue;
          scorePart = line.substring(0, spaceIndex).trim();
          out = line.substring(spaceIndex + 1).trim();
        }
        if (!scorePart || !out) continue;
        const rangeMatch = scorePart.match(/^(\d+)\s*(?:-|_|~|\/|to)\s*(\d+)$/i);
        if (rangeMatch) {
          const start = parseInt(rangeMatch[1], 10);
          const end = parseInt(rangeMatch[2], 10);
          if (!isNaN(start) && !isNaN(end) && start <= end) {
            parsed.push({ min_score: start, max_score: end, output_value: out });
          }
        } else {
          const raw = parseInt(scorePart, 10);
          if (!isNaN(raw)) {
            parsed.push({ min_score: raw, max_score: raw, output_value: out });
          }
        }
      }
      if (parsed.length === 0) {
        pasteError = "No valid rows found. Supported format:\n- 0-10 [tab] 85th\n- 15: 90th";
        return;
      }
      rows = [...rows, ...parsed];
      pasteOpen = false;
      pasteText = "";
    }
    function copyTable() {
      if (rows.length === 0) return;
      const text = collapseTable(rows).map((g) => g.start === g.end ? `${g.start}	${g.percentile}` : `${g.start}-${g.end}	${g.percentile}`).join("\n");
      navigator.clipboard?.writeText(text);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("sbtmbg", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(area.code ?? "Area")} scoring · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 w-full max-w-6xl space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/aptitude",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    size: "sm",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Back to aptitude areas`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Scoring configuration",
              description: `${area.name ?? ""} (${area.code ?? ""}) · v${area.version ?? 1}`,
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          ModeBanner($$renderer4, {
            variant: "warning",
            title: "Confidential — Test Administrator only",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->This conversion table decides every grade in the assessment. It is never shown to applicants,
      proctors, or Program Head. Treat edits as versioned changes: already-recorded scores keep the
      table that was live when they were entered.`);
            }
          });
          $$renderer4.push(`<!----> `);
          if (status()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-emerald-600" data-testid="scoring-status">${escape_html(status())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <form class="space-y-6 rounded-xl border border-border bg-card p-6"><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "max-items",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Maximum raw score`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "max-items",
            type: "number",
            min: "1",
            max: "1000",
            class: "min-h-10 w-32",
            get value() {
              return maxItems;
            },
            set value($$value) {
              maxItems = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p class="text-xs text-muted-foreground">Every score from 0 to this value must be covered by a row.</p></div> <div class="flex items-end justify-end gap-2">`);
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "gap-2",
            onclick: () => pasteOpen = true,
            children: ($$renderer5) => {
              Clipboard_paste($$renderer5, { class: "size-4" });
              $$renderer5.push(`<!----> Paste`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "gap-2",
            onclick: copyTable,
            disabled: rows.length === 0,
            children: ($$renderer5) => {
              Copy($$renderer5, { class: "size-4" });
              $$renderer5.push(`<!----> Copy`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></div> <div class="space-y-2"><div class="flex items-center justify-between">`);
          Label($$renderer4, {
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Conversion table`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "gap-2",
            onclick: addRow,
            children: ($$renderer5) => {
              Plus($$renderer5, { class: "size-4" });
              $$renderer5.push(`<!----> Add row`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> `);
          if (rows.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No rows yet — add a row or paste a table (e.g. <code class="font-mono">0-10 [tab] 85th</code>).</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="space-y-2"><!--[-->`);
            const each_array = ensure_array_like(rows);
            for (let i = 0, $$length = each_array.length; i < $$length; i++) {
              let row = each_array[i];
              $$renderer4.push(`<div class="grid grid-cols-[7rem_7rem_1fr_auto] items-center gap-2">`);
              Input($$renderer4, {
                "aria-label": `Row ${i + 1} minimum score`,
                type: "number",
                min: "0",
                max: "1000",
                value: row.min_score,
                oninput: (e) => row.min_score = e.currentTarget.value,
                class: "min-h-10"
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                "aria-label": `Row ${i + 1} maximum score`,
                type: "number",
                min: "0",
                max: "1000",
                value: row.max_score,
                oninput: (e) => row.max_score = e.currentTarget.value,
                class: "min-h-10"
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                "aria-label": `Row ${i + 1} output value`,
                type: "text",
                maxlength: "50",
                value: row.output_value,
                oninput: (e) => row.output_value = e.currentTarget.value,
                placeholder: "e.g. 85th",
                class: "min-h-10"
              });
              $$renderer4.push(`<!----> `);
              Button($$renderer4, {
                type: "button",
                variant: "ghost",
                size: "icon",
                "aria-label": `Remove row ${i + 1}`,
                onclick: () => removeRow(i),
                children: ($$renderer5) => {
                  Trash_2($$renderer5, { class: "size-4" });
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (saveError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive" data-testid="save-error">${escape_html(saveError())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex items-center gap-3 border-t border-border pt-4">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10 gap-2",
            disabled: rows.length === 0,
            children: ($$renderer5) => {
              Save($$renderer5, { class: "size-4" });
              $$renderer5.push(`<!----> ${escape_html("Save scoring configuration")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "outline",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->conversion_table`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form></div> `);
          if (pasteOpen) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="dialog" aria-modal="true"><div class="w-full max-w-lg rounded-xl border border-border bg-card p-5"><h2 class="text-lg font-semibold">Paste conversion table</h2> <p class="mt-1 text-sm text-muted-foreground">One range per line: <code class="font-mono">0-10 [tab] 85th</code>, <code class="font-mono">15: 90th</code>, or <code class="font-mono">20 92nd</code>. Ranges may be entered directly.</p> `);
            Textarea($$renderer4, {
              rows: "8",
              class: "mt-3 min-h-40 font-mono text-sm",
              placeholder: "0-49	Below Average\n50-74	Average\n75-89	Above Average\n90-100	Excellent",
              get value() {
                return pasteText;
              },
              set value($$value) {
                pasteText = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (pasteError) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-2 text-sm text-destructive">${escape_html(pasteError)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="mt-4 flex justify-end gap-2">`);
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              onclick: () => pasteOpen = false,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Cancel`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              type: "button",
              onclick: applyPaste,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Add rows`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]-->`);
        }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  AptitudeScoring as default
};
