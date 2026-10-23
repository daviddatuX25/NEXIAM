import { e as escape_html, B as Button, a as ensure_array_like, b as attr } from "../app.js";
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
function TestInstrumentEditor($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      mode,
      instrument = null,
      version = null,
      request_id,
      constructs = []
    } = $$props;
    const editing = mode === "edit";
    const initialProfile = version?.profile ?? {
      code: "",
      display_name: "",
      publisher_name: "",
      form_code: "",
      edition: "",
      language: "",
      source_reference: "",
      permitted_use_reference: ""
    };
    const form = useForm({
      request_id,
      ...editing ? { expected_revision: version.lock_version } : {},
      profile: { ...initialProfile },
      subtests: (version?.subtests ?? []).map((subtest) => ({
        ...subtest,
        items: (subtest.items ?? []).map((item) => ({ ...item, option_tokens: [...item.option_tokens ?? []] }))
      })),
      metrics: (version?.metrics ?? []).map((metric) => ({
        ...metric,
        input_codes: [...metric.input_codes ?? []],
        required_output_scales: [...metric.required_output_scales ?? []]
      }))
    });
    let editorNotice = "";
    function nextCode(prefix, rows) {
      let sequence = 1;
      while (rows.some((row) => row.code === `${prefix}_${sequence}`)) sequence++;
      return `${prefix}_${sequence}`;
    }
    function addSubtest() {
      const next = [...form.subtests];
      next.push({
        code: nextCode("SECTION", next),
        name: "",
        display_order: next.length + 1,
        response_mode: "keyed_items",
        item_order_policy: "fixed",
        items: []
      });
      form.subtests = next;
    }
    function removeSubtest(index) {
      const code = form.subtests[index]?.code;
      if (form.metrics.some((metric) => metric.subtest_code === code)) {
        editorNotice = "Update or remove metrics that reference this subtest before deleting it.";
        return;
      }
      form.subtests = form.subtests.filter((_, currentIndex) => currentIndex !== index).map((subtest, currentIndex) => ({ ...subtest, display_order: currentIndex + 1 }));
      editorNotice = "";
    }
    function addItem(subtestIndex) {
      const next = [...form.subtests];
      const subtest = { ...next[subtestIndex], items: [...next[subtestIndex].items] };
      subtest.items.push({
        item_code: nextCode("ITEM", subtest.items),
        local_number: "",
        display_order: subtest.items.length + 1,
        is_scored: true,
        option_tokens: []
      });
      next[subtestIndex] = subtest;
      form.subtests = next;
    }
    function removeItem(subtestIndex, itemIndex) {
      const next = [...form.subtests];
      const subtest = { ...next[subtestIndex] };
      subtest.items = subtest.items.filter((_, currentIndex) => currentIndex !== itemIndex).map((item, currentIndex) => ({ ...item, display_order: currentIndex + 1 }));
      next[subtestIndex] = subtest;
      form.subtests = next;
    }
    function addMetric() {
      const next = [...form.metrics];
      next.push({
        code: nextCode("RAW", next),
        name: "",
        rule_kind: "correct_item_count",
        subtest_code: form.subtests.find((subtest) => subtest.response_mode === "keyed_items")?.code ?? "",
        unit: "count",
        min_raw_value: null,
        max_raw_value: null,
        precision_scale: 0,
        is_required: true,
        required_output_scales: [],
        procedure_reference: "",
        construct_aptitude_area_id: null,
        input_codes: []
      });
      form.metrics = next;
    }
    function removeMetric(index) {
      const code = form.metrics[index]?.code;
      if (form.metrics.some((metric, metricIndex) => metricIndex !== index && metric.input_codes.includes(code))) {
        editorNotice = "Remove this metric from dependent raw sums before deleting it.";
        return;
      }
      form.metrics = form.metrics.filter((_, currentIndex) => currentIndex !== index);
      editorNotice = "";
    }
    const outputScales = [
      { value: "percentile_rank", label: "Percentile rank" },
      { value: "stanine", label: "Stanine" },
      { value: "category", label: "Source-defined category" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (editorNotice) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="status">${escape_html(editorNotice)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <form class="space-y-6"><section class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6" aria-labelledby="identity-heading"><div><h2 id="identity-heading" class="font-semibold">Instrument and form identity</h2> <p class="text-muted-foreground mt-1 text-sm">Enter source references only. Do not paste question text, keys,
                or norm-table values here.</p></div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "instrument-code",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Instrument code`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "instrument-code",
        maxlength: "64",
        class: "min-h-10",
        disabled: editing,
        required: true,
        get value() {
          return form.profile.code;
        },
        set value($$value) {
          form.profile.code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors["profile.code"]) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors["profile.code"])}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "profile-name",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Display name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "profile-name",
        maxlength: "255",
        class: "min-h-10",
        required: true,
        get value() {
          return form.profile.display_name;
        },
        set value($$value) {
          form.profile.display_name = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors["profile.display_name"]) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors["profile.display_name"])}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "publisher-name",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Publisher / source owner`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "publisher-name",
        maxlength: "255",
        class: "min-h-10",
        get value() {
          return form.profile.publisher_name;
        },
        set value($$value) {
          form.profile.publisher_name = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "form-code",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Form code`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "form-code",
        maxlength: "64",
        class: "min-h-10",
        get value() {
          return form.profile.form_code;
        },
        set value($$value) {
          form.profile.form_code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "edition",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Edition`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "edition",
        maxlength: "128",
        class: "min-h-10",
        get value() {
          return form.profile.edition;
        },
        set value($$value) {
          form.profile.edition = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "language",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Language`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "language",
        maxlength: "32",
        class: "min-h-10",
        get value() {
          return form.profile.language;
        },
        set value($$value) {
          form.profile.language = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "source-reference",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Official source reference`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "source-reference",
        maxlength: "500",
        class: "min-h-10",
        placeholder: "Manual title, edition, page or authorized catalog reference",
        get value() {
          return form.profile.source_reference;
        },
        set value($$value) {
          form.profile.source_reference = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> <p class="text-muted-foreground text-xs">Reference the source; do not upload or reproduce licensed
                    material.</p> `);
      if (form.errors["profile.source_reference"]) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors["profile.source_reference"])}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "permitted-use-reference",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Permitted-use / digitization reference`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "permitted-use-reference",
        maxlength: "500",
        class: "min-h-10",
        placeholder: "Authority or permission reference",
        get value() {
          return form.profile.permitted_use_reference;
        },
        set value($$value) {
          form.profile.permitted_use_reference = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors["profile.permitted_use_reference"]) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors["profile.permitted_use_reference"])}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div></section> <section class="border-border bg-card space-y-4 rounded-xl border p-4 sm:p-6" aria-labelledby="subtests-heading"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="subtests-heading" class="font-semibold">Test sections / subtests</h2> <p class="text-muted-foreground mt-1 text-sm">Local item numbers may restart in another subtest. Keep item
                    codes stable within this version.</p></div> `);
      Button($$renderer3, {
        type: "button",
        variant: "outline",
        class: "min-h-10 gap-2",
        onclick: addSubtest,
        children: ($$renderer4) => {
          Plus($$renderer4, { class: "h-4 w-4" });
          $$renderer4.push(`<!---->Add subtest`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> `);
      if (form.errors.subtests) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.subtests)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <!--[-->`);
      const each_array = ensure_array_like(form.subtests);
      for (let subtestIndex = 0, $$length = each_array.length; subtestIndex < $$length; subtestIndex++) {
        let subtest = each_array[subtestIndex];
        $$renderer3.push(`<article class="border-border/70 space-y-4 rounded-lg border p-4"><div class="flex flex-wrap items-start justify-between gap-3"><h3 class="font-medium">Subtest ${escape_html(subtestIndex + 1)}</h3> `);
        Button($$renderer3, {
          type: "button",
          size: "sm",
          variant: "destructive",
          onclick: () => removeSubtest(subtestIndex),
          children: ($$renderer4) => {
            Trash_2($$renderer4, { class: "h-4 w-4" });
            $$renderer4.push(`<!----><span class="sr-only">Remove subtest ${escape_html(subtestIndex + 1)}</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `subtest-code-${subtestIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Stable code`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        Input($$renderer3, {
          id: `subtest-code-${subtestIndex}`,
          maxlength: "32",
          class: "min-h-10",
          get value() {
            return subtest.code;
          },
          set value($$value) {
            subtest.code = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `subtest-name-${subtestIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Name`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        Input($$renderer3, {
          id: `subtest-name-${subtestIndex}`,
          maxlength: "255",
          class: "min-h-10",
          get value() {
            return subtest.name;
          },
          set value($$value) {
            subtest.name = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `subtest-mode-${subtestIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Response mode`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        $$renderer3.select(
          {
            id: `subtest-mode-${subtestIndex}`,
            value: subtest.response_mode,
            class: "border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "keyed_items" }, ($$renderer5) => {
              $$renderer5.push(`Keyed response items`);
            });
            $$renderer4.option({ value: "recorded_measure" }, ($$renderer5) => {
              $$renderer5.push(`Recorded measure`);
            });
          }
        );
        $$renderer3.push(`</div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `subtest-order-${subtestIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Item order`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        $$renderer3.select(
          {
            id: `subtest-order-${subtestIndex}`,
            value: subtest.item_order_policy,
            class: "border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "fixed" }, ($$renderer5) => {
              $$renderer5.push(`Fixed source order`);
            });
            $$renderer4.option({ value: "within_subtest_permutable" }, ($$renderer5) => {
              $$renderer5.push(`Permutable within this subtest`);
            });
          }
        );
        $$renderer3.push(`</div></div> `);
        if (subtest.item_order_policy === "within_subtest_permutable") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">Approval requires a verified source that permits this
                        within-subtest permutation. Cross-subtest shuffling is
                        not supported.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (subtest.response_mode === "keyed_items") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border space-y-3 border-t pt-4"><div class="flex flex-wrap items-center justify-between gap-2"><div><h4 class="text-sm font-semibold">Item inventory</h4> <p class="text-muted-foreground text-xs">Identity and response tokens only. Enter
                                    question content and answer keys in
                                    Booklets.</p></div> `);
          Button($$renderer3, {
            type: "button",
            size: "sm",
            variant: "outline",
            class: "min-h-9 gap-2",
            onclick: () => addItem(subtestIndex),
            children: ($$renderer4) => {
              Plus($$renderer4, { class: "h-4 w-4" });
              $$renderer4.push(`<!---->Add item`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> <!--[-->`);
          const each_array_1 = ensure_array_like(subtest.items);
          for (let itemIndex = 0, $$length2 = each_array_1.length; itemIndex < $$length2; itemIndex++) {
            let item = each_array_1[itemIndex];
            $$renderer3.push(`<div class="bg-muted/20 grid gap-3 rounded-lg p-3 sm:grid-cols-2 lg:grid-cols-4"><div class="space-y-1">`);
            Label($$renderer3, {
              for: `item-code-${subtestIndex}-${itemIndex}`,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Item code`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Input($$renderer3, {
              id: `item-code-${subtestIndex}-${itemIndex}`,
              maxlength: "64",
              class: "min-h-9",
              get value() {
                return item.item_code;
              },
              set value($$value) {
                item.item_code = $$value;
                $$settled = false;
              }
            });
            $$renderer3.push(`<!----></div> <div class="space-y-1">`);
            Label($$renderer3, {
              for: `item-number-${subtestIndex}-${itemIndex}`,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Printed local number`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Input($$renderer3, {
              id: `item-number-${subtestIndex}-${itemIndex}`,
              maxlength: "32",
              class: "min-h-9",
              get value() {
                return item.local_number;
              },
              set value($$value) {
                item.local_number = $$value;
                $$settled = false;
              }
            });
            $$renderer3.push(`<!----></div> <div class="space-y-1">`);
            Label($$renderer3, {
              for: `item-tokens-${subtestIndex}-${itemIndex}`,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Response tokens`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Input($$renderer3, {
              id: `item-tokens-${subtestIndex}-${itemIndex}`,
              value: item.option_tokens.join(", "),
              oninput: (event) => {
                const tokens = event.currentTarget.value.split(",").map((token) => token.trim()).filter((token) => token !== "");
                const next = [...form.subtests];
                const row = { ...next[subtestIndex], items: [...next[subtestIndex].items] };
                row.items[itemIndex] = { ...row.items[itemIndex], option_tokens: tokens };
                next[subtestIndex] = row;
                form.subtests = next;
              },
              class: "min-h-9",
              placeholder: "A, B, C, D"
            });
            $$renderer3.push(`<!----></div> `);
            Button($$renderer3, {
              type: "button",
              size: "sm",
              variant: "destructive",
              onclick: () => removeItem(subtestIndex, itemIndex),
              children: ($$renderer4) => {
                Trash_2($$renderer4, { class: "h-4 w-4" });
                $$renderer4.push(`<!----><span class="sr-only">Remove item ${escape_html(item.local_number || itemIndex + 1)}</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-4"><input type="checkbox"${attr("checked", item.is_scored, true)} class="accent-primary size-4"/>Scored item (leave unchecked for
                                    practice/unscored items)</label></div>`);
          }
          $$renderer3.push(`<!--]--> `);
          if (form.errors[`subtests.${subtestIndex}.items`]) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors[`subtests.${subtestIndex}.items`])}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="border-border bg-muted/20 text-muted-foreground rounded-md border p-3 text-sm">No answer items are created for a recorded-measure
                        section. The procedure, unit, precision, and bounds must
                        be verified before approval.</p> `);
          if (subtest.items.length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<ul class="space-y-2" role="list"><!--[-->`);
            const each_array_2 = ensure_array_like(subtest.items);
            for (let itemIndex = 0, $$length2 = each_array_2.length; itemIndex < $$length2; itemIndex++) {
              let item = each_array_2[itemIndex];
              $$renderer3.push(`<li class="border-border flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"><span>${escape_html(item.item_code)} · local number ${escape_html(item.local_number || "unassigned")}</span> `);
              Button($$renderer3, {
                type: "button",
                size: "sm",
                variant: "outline",
                onclick: () => removeItem(subtestIndex, itemIndex),
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Remove incompatible item row`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--></article>`);
      }
      $$renderer3.push(`<!--]--></section> <section class="border-border bg-card space-y-4 rounded-xl border p-4 sm:p-6" aria-labelledby="metrics-heading"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="metrics-heading" class="font-semibold">Raw metrics and bounded rules</h2> <p class="text-muted-foreground mt-1 text-sm">Only correct-item count, compatible raw-metric sum, and
                    source-defined recorded measure rules are supported. No
                    formulas or scripts.</p></div> `);
      Button($$renderer3, {
        type: "button",
        variant: "outline",
        class: "min-h-10 gap-2",
        onclick: addMetric,
        children: ($$renderer4) => {
          Plus($$renderer4, { class: "h-4 w-4" });
          $$renderer4.push(`<!---->Add metric`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> `);
      if (form.errors.metrics) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.metrics)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <!--[-->`);
      const each_array_3 = ensure_array_like(form.metrics);
      for (let metricIndex = 0, $$length = each_array_3.length; metricIndex < $$length; metricIndex++) {
        let metric = each_array_3[metricIndex];
        $$renderer3.push(`<article class="border-border/70 space-y-4 rounded-lg border p-4"><div class="flex flex-wrap items-start justify-between gap-3"><h3 class="font-medium">Raw metric ${escape_html(metricIndex + 1)}</h3> `);
        Button($$renderer3, {
          type: "button",
          size: "sm",
          variant: "destructive",
          onclick: () => removeMetric(metricIndex),
          children: ($$renderer4) => {
            Trash_2($$renderer4, { class: "h-4 w-4" });
            $$renderer4.push(`<!----><span class="sr-only">Remove metric ${escape_html(metricIndex + 1)}</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `metric-code-${metricIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Stable code`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        Input($$renderer3, {
          id: `metric-code-${metricIndex}`,
          maxlength: "32",
          class: "min-h-10",
          get value() {
            return metric.code;
          },
          set value($$value) {
            metric.code = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `metric-name-${metricIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Metric name`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        Input($$renderer3, {
          id: `metric-name-${metricIndex}`,
          maxlength: "255",
          class: "min-h-10",
          get value() {
            return metric.name;
          },
          set value($$value) {
            metric.name = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `metric-rule-${metricIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Rule`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        $$renderer3.select(
          {
            id: `metric-rule-${metricIndex}`,
            value: metric.rule_kind,
            class: "border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "correct_item_count" }, ($$renderer5) => {
              $$renderer5.push(`Correct-item count`);
            });
            $$renderer4.option({ value: "sum_raw_metrics" }, ($$renderer5) => {
              $$renderer5.push(`Sum compatible raw metrics`);
            });
            $$renderer4.option({ value: "recorded_measure" }, ($$renderer5) => {
              $$renderer5.push(`Recorded measure`);
            });
          }
        );
        $$renderer3.push(`</div> `);
        if (metric.rule_kind === "correct_item_count" || metric.rule_kind === "recorded_measure") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-1.5">`);
          Label($$renderer3, {
            for: `metric-subtest-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Test section / subtest`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          $$renderer3.select(
            {
              id: `metric-subtest-${metricIndex}`,
              value: metric.subtest_code,
              class: "border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "" }, ($$renderer5) => {
                $$renderer5.push(`Select a subtest`);
              });
              $$renderer4.push(`<!--[-->`);
              const each_array_4 = ensure_array_like(form.subtests);
              for (let subtestChoiceIndex = 0, $$length2 = each_array_4.length; subtestChoiceIndex < $$length2; subtestChoiceIndex++) {
                let subtest = each_array_4[subtestChoiceIndex];
                $$renderer4.option({ value: subtest.code }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(subtest.name || subtest.code)}`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(`</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (metric.rule_kind === "recorded_measure") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-1.5">`);
          Label($$renderer3, {
            for: `metric-unit-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Source-defined unit`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Input($$renderer3, {
            id: `metric-unit-${metricIndex}`,
            maxlength: "64",
            class: "min-h-10",
            get value() {
              return metric.unit;
            },
            set value($$value) {
              metric.unit = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: `metric-precision-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Decimal places (0–6)`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Input($$renderer3, {
            id: `metric-precision-${metricIndex}`,
            type: "number",
            min: "0",
            max: "6",
            class: "min-h-10",
            get value() {
              return metric.precision_scale;
            },
            set value($$value) {
              metric.precision_scale = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: `metric-min-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Minimum raw value`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Input($$renderer3, {
            id: `metric-min-${metricIndex}`,
            inputmode: "decimal",
            class: "min-h-10",
            get value() {
              return metric.min_raw_value;
            },
            set value($$value) {
              metric.min_raw_value = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: `metric-max-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Maximum raw value`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Input($$renderer3, {
            id: `metric-max-${metricIndex}`,
            inputmode: "decimal",
            class: "min-h-10",
            get value() {
              return metric.max_raw_value;
            },
            set value($$value) {
              metric.max_raw_value = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer3, {
            for: `metric-procedure-${metricIndex}`,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Procedure reference`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Input($$renderer3, {
            id: `metric-procedure-${metricIndex}`,
            maxlength: "500",
            class: "min-h-10",
            get value() {
              return metric.procedure_reference;
            },
            set value($$value) {
              metric.procedure_reference = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (metric.rule_kind === "sum_raw_metrics") {
          $$renderer3.push("<!--[1-->");
          const candidateCodes = form.metrics.filter((_, idx) => idx !== metricIndex).map((c) => c.code);
          const allSelected = candidateCodes.length > 0 && candidateCodes.every((c) => metric.input_codes.includes(c));
          $$renderer3.push(`<fieldset class="space-y-2 sm:col-span-2 lg:col-span-3"><div class="flex items-center justify-between"><legend class="text-sm font-medium">Component raw metrics (choose at least two)</legend> `);
          if (candidateCodes.length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="flex items-center gap-3 text-xs"><button type="button" class="text-primary hover:underline font-medium cursor-pointer">${escape_html(allSelected ? "Deselect all" : `Select all (${candidateCodes.length})`)}</button> `);
            if (metric.input_codes.length > 0) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="text-muted-foreground hover:text-destructive hover:underline cursor-pointer">Clear</button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
          const each_array_5 = ensure_array_like(form.metrics);
          for (let candidateIndex = 0, $$length2 = each_array_5.length; candidateIndex < $$length2; candidateIndex++) {
            let candidate = each_array_5[candidateIndex];
            if (candidateIndex !== metricIndex) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<label class="border-border flex items-center gap-2 rounded-md border p-2 text-sm"><input type="checkbox"${attr("checked", metric.input_codes.includes(candidate.code), true)} class="accent-primary size-4"/>${escape_html(candidate.name || candidate.code)}</label>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          }
          $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground text-xs">Bounds, units, and precision derive from the
                                same-version compatible inputs.</p></fieldset>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="border-border bg-muted/20 text-muted-foreground rounded-md border p-3 text-sm sm:col-span-2 lg:col-span-3">Bounds derive from the exact scored-item inventory;
                            they are not an item maximum typed by staff.</p>`);
        }
        $$renderer3.push(`<!--]--> <div class="space-y-1.5 sm:col-span-2 lg:col-span-3">`);
        Label($$renderer3, {
          for: `metric-construct-${metricIndex}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Optional aptitude construct mapping`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        $$renderer3.select(
          {
            id: `metric-construct-${metricIndex}`,
            value: metric.construct_aptitude_area_id,
            class: "border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`No construct mapping`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array_6 = ensure_array_like(constructs);
            for (let $$index_5 = 0, $$length2 = each_array_6.length; $$index_5 < $$length2; $$index_5++) {
              let construct = each_array_6[$$index_5];
              $$renderer4.option({ value: construct.id }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(construct.name)} (${escape_html(construct.code)})`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(` <p class="text-muted-foreground text-xs">A shared construct name does not make scores from
                            different instruments comparable.</p></div> <fieldset class="space-y-2 sm:col-span-2 lg:col-span-3"><legend class="text-sm font-medium">Required output scales</legend> <div class="flex flex-wrap gap-3"><!--[-->`);
        const each_array_7 = ensure_array_like(outputScales);
        for (let $$index_6 = 0, $$length2 = each_array_7.length; $$index_6 < $$length2; $$index_6++) {
          let scale = each_array_7[$$index_6];
          $$renderer3.push(`<label class="flex items-center gap-2 text-sm"><input type="checkbox"${attr("checked", metric.required_output_scales.includes(scale.value), true)} class="accent-primary size-4"/>${escape_html(scale.label)}</label>`);
        }
        $$renderer3.push(`<!--]--></div></fieldset> <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-3"><input type="checkbox"${attr("checked", metric.is_required, true)} class="accent-primary size-4"/>Required raw metric for this instrument version</label></div></article>`);
      }
      $$renderer3.push(`<!--]--></section> `);
      if (Object.keys(form.errors).length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-destructive/40 bg-destructive/5 rounded-lg border p-4 text-sm" role="alert"><h2 class="font-semibold">Review the highlighted profile fields</h2> <ul class="mt-2 list-disc space-y-1 pl-5"><!--[-->`);
        const each_array_8 = ensure_array_like(Object.entries(form.errors));
        for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
          let [field, error] = each_array_8[$$index_8];
          $$renderer3.push(`<li>${escape_html(error)}</li>`);
        }
        $$renderer3.push(`<!--]--></ul></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-2">`);
      Button($$renderer3, {
        type: "submit",
        class: "min-h-11",
        disabled: form.processing,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(form.processing ? "Saving…" : "Save draft")}`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: editing ? `/setup/test-instruments/${instrument.id}` : "/setup/test-instruments",
        children: ($$renderer4) => {
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "min-h-11",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
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
  TestInstrumentEditor as default
};
