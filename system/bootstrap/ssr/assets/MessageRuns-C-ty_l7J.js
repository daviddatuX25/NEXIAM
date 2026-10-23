import { b as attr, a as ensure_array_like, e as escape_html } from "../app.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function MessageRuns($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      value = [],
      label = "Text",
      tokens = [],
      scopeKey = "message",
      readOnly = false,
      onChanged = (_runs) => {
      }
    } = $$props;
    let linkSelection = null;
    let linkUrl = "";
    let status = "";
    function selectToken(index, token) {
      const next = value.map((run, runIndex) => {
        if (runIndex !== index) return { ...run };
        const changed = { ...run, token };
        delete changed.text;
        return changed;
      });
      onChanged(next);
    }
    function insertToken(index, token) {
      value[index];
      {
        status = "Focus a text run and choose its insertion point before adding a token.";
        return;
      }
    }
    $$renderer2.push(`<div class="space-y-3"${attr("aria-label", label)}><!--[-->`);
    const each_array = ensure_array_like(value);
    for (let index = 0, $$length = each_array.length; index < $$length; index++) {
      let run = each_array[index];
      $$renderer2.push(`<article class="min-w-0 space-y-2 rounded-lg border border-border p-3">`);
      if (typeof run.token === "string") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<label class="block space-y-1.5"><span class="text-sm font-medium">${escape_html(label)} token run ${escape_html(index + 1)}</span>`);
        $$renderer2.select(
          {
            disabled: readOnly,
            class: "min-h-11 w-full rounded-md border border-input bg-background px-3",
            value: run.token,
            onchange: (event) => selectToken(index, event.currentTarget.value)
          },
          ($$renderer3) => {
            $$renderer3.push(`<!--[-->`);
            const each_array_1 = ensure_array_like(tokens);
            for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
              let item = each_array_1[$$index];
              $$renderer3.option({ value: item.token }, ($$renderer4) => {
                $$renderer4.push(`${escape_html(item.label)}`);
              });
            }
            $$renderer3.push(`<!--]-->`);
          }
        );
        $$renderer2.push(`</label> <p class="text-sm text-muted-foreground">Inserted token: <code>${escape_html(run.token)}</code>. It remains a typed token run.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<label class="block space-y-1.5"><span class="text-sm font-medium">${escape_html(label)} text run ${escape_html(index + 1)}</span><textarea${attr("disabled", readOnly, true)}${attr("id", `message-run-${scopeKey}-${index}`)} class="min-h-20 w-full rounded-md border border-input bg-background p-3">`);
        const $$body = escape_html(String(run.text ?? ""));
        if ($$body) {
          $$renderer2.push(`${$$body}`);
        }
        $$renderer2.push(`</textarea></label> <div class="flex flex-wrap gap-2" role="group"${attr("aria-label", `Formatting for ${label} text run ${index + 1}`)}><button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("aria-pressed", Boolean(run.bold))}>Bold</button> <button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("aria-pressed", Boolean(run.italic))}>Italic</button> <button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("aria-pressed", Boolean(run.underline))}>Underline</button> <button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Link</button> <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">Insert token`);
        $$renderer2.select(
          {
            disabled: readOnly,
            "aria-label": `Insert token in ${label} text run ${index + 1}`,
            class: "max-w-48 bg-background",
            value: "",
            onchange: (event) => event.currentTarget.value && insertToken(index, event.currentTarget.value)
          },
          ($$renderer3) => {
            $$renderer3.option({ value: "" }, ($$renderer4) => {
              $$renderer4.push(`Choose token`);
            });
            $$renderer3.push(`<!--[-->`);
            const each_array_2 = ensure_array_like(tokens);
            for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
              let item = each_array_2[$$index_1];
              $$renderer3.option({ value: item.token }, ($$renderer4) => {
                $$renderer4.push(`${escape_html(item.label)}`);
              });
            }
            $$renderer3.push(`<!--]-->`);
          }
        );
        $$renderer2.push(`</label></div> `);
        if (linkSelection?.index === index) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex flex-wrap items-end gap-2"><label class="min-w-0 flex-1 space-y-1.5"><span class="block text-sm font-medium">Link destination URL</span><input${attr("disabled", readOnly, true)} type="url" class="min-h-11 w-full rounded-md border border-input bg-background px-3"${attr("value", linkUrl)}/></label><button${attr("disabled", readOnly, true)} type="button" class="min-h-11 rounded-md bg-primary px-4 text-primary-foreground">Apply link</button><button${attr("disabled", readOnly, true)} type="button" class="min-h-11 rounded-md border px-4">Cancel link</button></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--> <button${attr("disabled", readOnly, true)} type="button" class="min-h-10 text-sm text-destructive underline underline-offset-4"${attr("aria-label", `Remove ${label} run ${index + 1}`)}>Remove run</button></article>`);
    }
    $$renderer2.push(`<!--]--> <div class="flex flex-wrap gap-2"><button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Add text run</button>`);
    if (tokens.length) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button${attr("disabled", readOnly, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Add token run</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (status) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p role="status" class="text-sm text-muted-foreground">${escape_html(status)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  MessageRuns as default
};
