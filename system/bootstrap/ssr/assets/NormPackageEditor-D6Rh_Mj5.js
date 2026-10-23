import { e as escape_html, B as Button, a as ensure_array_like } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./Icon-Dal7PqKK.js";
function NormPackageEditor($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      mode = "create",
      package: normPackage = null,
      version = null,
      is_successor = false,
      request_id
    } = $$props;
    const editing = mode === "edit";
    const cancelHref = editing ? `/setup/norm-packages/${normPackage.id}` : "/setup/norm-packages";
    const form = useForm({
      request_id,
      ...editing && !is_successor ? { expected_revision: version.lock_version } : {},
      code: normPackage?.code ?? "",
      name: version?.display_name ?? "",
      publisher_name: version?.publisher_name ?? "",
      form_code: version?.form_code ?? "",
      edition: version?.edition ?? "",
      language: version?.language ?? "",
      applicable_population: version?.applicable_population ?? "",
      source_reference: version?.source_reference ?? "",
      permitted_use_reference: version?.permitted_use_reference ?? "",
      tables: (version?.tables ?? []).map((table) => ({
        metric_code: table.metric_code,
        output_scale: table.output_scale,
        intervals: (table.intervals ?? []).map((interval) => ({
          min_score: interval.min_score,
          max_score: interval.max_score,
          output_value: interval.output_value
        }))
      }))
    });
    const outputScales = [
      { value: "percentile_rank", label: "Percentile rank" },
      { value: "stanine", label: "Stanine" }
    ];
    function addTable() {
      form.tables = [
        ...form.tables,
        {
          metric_code: "",
          output_scale: "percentile_rank",
          intervals: []
        }
      ];
    }
    function updateTable(tableIndex, field, value) {
      form.tables = form.tables.map((table, index) => index === tableIndex ? { ...table, [field]: value } : table);
    }
    function removeTable(tableIndex) {
      form.tables = form.tables.filter((_, index) => index !== tableIndex);
    }
    function addInterval(tableIndex) {
      form.tables = form.tables.map((table, index) => index === tableIndex ? {
        ...table,
        intervals: [
          ...table.intervals,
          { min_score: "", max_score: "", output_value: "" }
        ]
      } : table);
    }
    function updateInterval(tableIndex, intervalIndex, field, value) {
      form.tables = form.tables.map((table, index) => index === tableIndex ? {
        ...table,
        intervals: table.intervals.map((interval, rowIndex) => rowIndex === intervalIndex ? { ...interval, [field]: value } : interval)
      } : table);
    }
    function removeInterval(tableIndex, intervalIndex) {
      form.tables = form.tables.map((table, index) => index === tableIndex ? {
        ...table,
        intervals: table.intervals.filter((_, rowIndex) => rowIndex !== intervalIndex)
      } : table);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (editing && normPackage.is_synthetic) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">Synthetic · software only. This package cannot be used for institutional scoring or activation.</div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <form class="space-y-6"><section class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6" aria-labelledby="identity-heading"><div><h2 id="identity-heading" class="font-semibold">Package and source identity</h2> <p class="text-muted-foreground mt-1 text-sm">Enter source references only. A package name or populated table does not establish scientific validity or applicability.</p></div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-code",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Package code`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-code",
        maxlength: "64",
        class: "min-h-10",
        disabled: editing,
        required: true,
        get value() {
          return form.code;
        },
        set value($$value) {
          form.code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.code) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.code)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-name",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Package name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-name",
        maxlength: "255",
        class: "min-h-10",
        required: true,
        get value() {
          return form.name;
        },
        set value($$value) {
          form.name = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.name) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.name)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-publisher",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Publisher`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-publisher",
        maxlength: "255",
        class: "min-h-10",
        get value() {
          return form.publisher_name;
        },
        set value($$value) {
          form.publisher_name = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.publisher_name) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.publisher_name)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-form",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Form code`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-form",
        maxlength: "64",
        class: "min-h-10",
        required: true,
        get value() {
          return form.form_code;
        },
        set value($$value) {
          form.form_code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.form_code) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.form_code)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-edition",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Edition`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-edition",
        maxlength: "128",
        class: "min-h-10",
        required: true,
        get value() {
          return form.edition;
        },
        set value($$value) {
          form.edition = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.edition) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.edition)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "norm-language",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Language`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-language",
        maxlength: "128",
        class: "min-h-10",
        required: true,
        get value() {
          return form.language;
        },
        set value($$value) {
          form.language = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.language) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.language)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "norm-population",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Applicable population`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "norm-population",
        maxlength: "255",
        class: "min-h-10",
        get value() {
          return form.applicable_population;
        },
        set value($$value) {
          form.applicable_population = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.applicable_population) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.applicable_population)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "norm-source-reference",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Source reference`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> <textarea id="norm-source-reference" rows="3" maxlength="2048" class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2">`);
      const $$body = escape_html(form.source_reference);
      if ($$body) {
        $$renderer3.push(`${$$body}`);
      }
      $$renderer3.push(`</textarea> `);
      if (form.errors.source_reference) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.source_reference)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "norm-permitted-use",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Permitted use reference`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> <textarea id="norm-permitted-use" rows="3" maxlength="2048" class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2">`);
      const $$body_1 = escape_html(form.permitted_use_reference);
      if ($$body_1) {
        $$renderer3.push(`${$$body_1}`);
      }
      $$renderer3.push(`</textarea> `);
      if (form.errors.permitted_use_reference) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.permitted_use_reference)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div></section> <section class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6" aria-labelledby="tables-heading"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 id="tables-heading" class="font-semibold">Conversion tables</h2> <p class="text-muted-foreground mt-1 text-sm">Keep each raw metric and output scale explicit. Enter only source-defined intervals; do not infer missing values or endpoints.</p></div> `);
      Button($$renderer3, {
        type: "button",
        variant: "outline",
        class: "min-h-10 gap-2",
        onclick: addTable,
        children: ($$renderer4) => {
          Plus($$renderer4, { class: "h-4 w-4" });
          $$renderer4.push(`<!---->Add table`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> `);
      if (form.errors.tables) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.tables)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (form.tables.length === 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-sm">No tables added. A draft can be saved without tables and remains unavailable for conversion and approval until its required tables are verified.</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="space-y-4"><!--[-->`);
      const each_array = ensure_array_like(form.tables);
      for (let tableIndex = 0, $$length = each_array.length; tableIndex < $$length; tableIndex++) {
        let table = each_array[tableIndex];
        $$renderer3.push(`<fieldset class="border-border min-w-0 space-y-4 rounded-lg border p-4"><legend class="px-1 text-sm font-medium">Table ${escape_html(tableIndex + 1)}</legend> <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"><div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `metric-${tableIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Raw metric code`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Input($$renderer3, {
          id: `metric-${tableIndex}`,
          value: table.metric_code,
          maxlength: "64",
          class: "min-h-10",
          oninput: (event) => updateTable(tableIndex, "metric_code", event.currentTarget.value)
        });
        $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `scale-${tableIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Output scale`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        $$renderer3.select(
          {
            id: `scale-${tableIndex}`,
            value: table.output_scale,
            onchange: (event) => updateTable(tableIndex, "output_scale", event.currentTarget.value),
            class: "border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.push(`<!--[-->`);
            const each_array_1 = ensure_array_like(outputScales);
            for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
              let scale = each_array_1[$$index];
              $$renderer4.option({ value: scale.value }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(scale.label)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</div> `);
        Button($$renderer3, {
          type: "button",
          variant: "ghost",
          class: "min-h-10 gap-2 text-destructive hover:text-destructive",
          onclick: () => removeTable(tableIndex),
          children: ($$renderer4) => {
            Trash_2($$renderer4, { class: "h-4 w-4" });
            $$renderer4.push(`<!---->Remove`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        if (form.errors[`tables.${tableIndex}.metric_code`]) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors[`tables.${tableIndex}.metric_code`])}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (form.errors[`tables.${tableIndex}.output_scale`]) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors[`tables.${tableIndex}.output_scale`])}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="overflow-x-auto rounded-lg border border-border"><table class="w-full min-w-150 text-sm"><thead class="bg-muted/50 text-left"><tr><th class="px-3 py-2 font-medium">min</th><th class="px-3 py-2 font-medium">max</th><th class="px-3 py-2 font-medium">${escape_html(table.output_scale)}</th><th class="w-20 px-3 py-2"><span class="sr-only">Remove row</span></th></tr></thead><tbody>`);
        const each_array_2 = ensure_array_like(table.intervals);
        if (each_array_2.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let intervalIndex = 0, $$length2 = each_array_2.length; intervalIndex < $$length2; intervalIndex++) {
            let interval = each_array_2[intervalIndex];
            $$renderer3.push(`<tr class="border-t border-border"><td class="px-3 py-2">`);
            Input($$renderer3, {
              type: "number",
              step: "1",
              value: interval.min_score,
              class: "min-h-10 min-w-24",
              "aria-label": `Minimum score, table ${tableIndex + 1}, row ${intervalIndex + 1}`,
              oninput: (event) => updateInterval(tableIndex, intervalIndex, "min_score", event.currentTarget.value)
            });
            $$renderer3.push(`<!----> `);
            if (form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.min_score`]) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-destructive mt-1 text-xs" role="alert">${escape_html(form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.min_score`])}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></td><td class="px-3 py-2">`);
            Input($$renderer3, {
              type: "number",
              step: "1",
              value: interval.max_score,
              class: "min-h-10 min-w-24",
              "aria-label": `Maximum score, table ${tableIndex + 1}, row ${intervalIndex + 1}`,
              oninput: (event) => updateInterval(tableIndex, intervalIndex, "max_score", event.currentTarget.value)
            });
            $$renderer3.push(`<!----> `);
            if (form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.max_score`]) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-destructive mt-1 text-xs" role="alert">${escape_html(form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.max_score`])}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></td><td class="px-3 py-2">`);
            Input($$renderer3, {
              value: interval.output_value,
              inputmode: "decimal",
              maxlength: "64",
              class: "min-h-10 min-w-32",
              "aria-label": `${table.output_scale} output, table ${tableIndex + 1}, row ${intervalIndex + 1}`,
              oninput: (event) => updateInterval(tableIndex, intervalIndex, "output_value", event.currentTarget.value)
            });
            $$renderer3.push(`<!----> `);
            if (form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.output_value`]) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-destructive mt-1 text-xs" role="alert">${escape_html(form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.output_value`])}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></td><td class="px-3 py-2 text-right">`);
            Button($$renderer3, {
              type: "button",
              variant: "ghost",
              size: "icon",
              class: "size-10 text-muted-foreground",
              "aria-label": `Remove interval ${intervalIndex + 1}`,
              onclick: () => removeInterval(tableIndex, intervalIndex),
              children: ($$renderer4) => {
                Trash_2($$renderer4, { class: "h-4 w-4" });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></td></tr>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<tr><td colspan="4" class="text-muted-foreground px-3 py-4 text-center">No intervals. Add source-defined ranges when available.</td></tr>`);
        }
        $$renderer3.push(`<!--]--></tbody></table></div> `);
        if (form.errors[`tables.${tableIndex}.intervals`]) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors[`tables.${tableIndex}.intervals`])}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-10 gap-2",
          onclick: () => addInterval(tableIndex),
          children: ($$renderer4) => {
            Plus($$renderer4, { class: "h-4 w-4" });
            $$renderer4.push(`<!---->Add interval`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></fieldset>`);
      }
      $$renderer3.push(`<!--]--></div></section> <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">`);
      Link($$renderer3, {
        href: cancelHref,
        children: ($$renderer4) => {
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "min-h-11 w-full sm:w-auto",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Button($$renderer3, {
        type: "submit",
        class: "min-h-11",
        disabled: form.processing,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(form.processing ? "Saving…" : is_successor ? "Create successor draft" : "Save draft")}`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div></form>`);
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
  NormPackageEditor as default
};
