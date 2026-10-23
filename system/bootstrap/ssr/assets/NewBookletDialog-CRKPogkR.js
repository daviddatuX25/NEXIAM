import { b as attr, c as attr_class, e as escape_html, a as ensure_array_like, B as Button, i as spread_props } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { D as Dialog_trigger } from "./dialog-trigger-46d4JmXJ.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { router } from "@inertiajs/core";
function NewBookletDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { answer_sheets = [], can_view_question_content = false } = $$props;
    const selectClass = "h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";
    let open = false;
    let name = `ICAT Answerkeys ${(/* @__PURE__ */ new Date()).getFullYear()}`;
    let mode = "answer_keys_only";
    let templateVersionId = answer_sheets.length === 1 ? answer_sheets[0].id : "";
    let saving = false;
    function createBooklet() {
      if (saving || !name.trim()) return;
      saving = true;
      router.post(
        "/setup/exam-materials/booklets",
        {
          name: name.trim(),
          authoring_mode: mode,
          template_version_id: templateVersionId ? Number(templateVersionId) : null
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            open = false;
            name = "";
          },
          onFinish: () => saving = false
        }
      );
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Dialog($$renderer3, {
        get open() {
          return open;
        },
        set open($$value) {
          open = $$value;
          $$settled = false;
        },
        children: ($$renderer4) => {
          {
            let child = function($$renderer5, { props }) {
              Button($$renderer5, spread_props([
                props,
                {
                  class: "min-h-11 gap-2",
                  children: ($$renderer6) => {
                    Plus($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!---->New Booklet`);
                  },
                  $$slots: { default: true }
                }
              ]));
            };
            Dialog_trigger($$renderer4, { child, $$slots: { child: true } });
          }
          $$renderer4.push(`<!----> `);
          Dialog_content($$renderer4, {
            class: "sm:max-w-lg",
            children: ($$renderer5) => {
              Dialog_header($$renderer5, {
                children: ($$renderer6) => {
                  Dialog_title($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->New Booklet`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Dialog_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Choose how this Booklet's questions and keys are managed.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> <div class="space-y-5 py-2"><div class="space-y-2">`);
              Label($$renderer5, {
                for: "new-booklet-name",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Booklet name`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Input($$renderer5, {
                id: "new-booklet-name",
                placeholder: `e.g. ICAT Answerkeys ${(/* @__PURE__ */ new Date()).getFullYear()}`,
                get value() {
                  return name;
                },
                set value($$value) {
                  name = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div> <fieldset class="space-y-2"><legend class="flex w-full items-center justify-between pb-1 text-sm font-medium"><span>Booklet Mode &amp; Questions</span> <span class="text-xs font-normal text-muted-foreground">Default: Answer keys only</span></legend> <div class="grid gap-2.5"><label class="border-border has-checked:border-sky-500 has-checked:bg-sky-500/5 dark:has-checked:bg-sky-500/10 relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all"><input type="radio"${attr("checked", mode === "answer_keys_only", true)} value="answer_keys_only" class="mt-1 size-4 text-sky-600 focus:ring-sky-500"/> <div class="min-w-0 flex-1 space-y-1"><div class="flex flex-wrap items-center gap-2">`);
              Key_round($$renderer5, { class: "size-4 text-sky-600 dark:text-sky-400 shrink-0" });
              $$renderer5.push(`<!----> <strong class="text-sm font-semibold text-foreground">Answer keys only (External paper booklets)</strong> `);
              Badge($$renderer5, {
                variant: "outline",
                class: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[10px] font-bold",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Default · Recommended`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div> <p class="text-muted-foreground text-xs leading-relaxed">Questions remain in externally printed paper booklets. Staff only enter the correct scoring keys (A, B, C, D) for automated OMR grading.</p> `);
              {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-sky-700 dark:text-sky-300">`);
                Circle_check($$renderer5, { class: "size-3.5" });
                $$renderer5.push(`<!----> <span>Fast setup: No question stems or choices required in NEXIAM.</span></div>`);
              }
              $$renderer5.push(`<!--]--></div></label> <label${attr_class("border-border has-checked:border-purple-500 has-checked:bg-purple-500/5 dark:has-checked:bg-purple-500/10 relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all", void 0, { "opacity-50": !can_view_question_content })}><input type="radio"${attr("checked", mode === "stored_questions", true)} value="stored_questions"${attr("disabled", !can_view_question_content, true)} class="mt-1 size-4 text-purple-600 focus:ring-purple-500"/> <div class="min-w-0 flex-1 space-y-1"><div class="flex flex-wrap items-center gap-2">`);
              File_text($$renderer5, {
                class: "size-4 text-purple-600 dark:text-purple-400 shrink-0"
              });
              $$renderer5.push(`<!----> <strong class="text-sm font-semibold text-foreground">Questions and answers (Stored questions)</strong></div> <p class="text-muted-foreground text-xs leading-relaxed">Author and securely store questions and choices directly inside NEXIAM. Use this only if maintaining an internal digital question bank.</p> `);
              if (!can_view_question_content) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<p class="text-xs text-amber-600 dark:text-amber-400">Requires question authoring permissions.</p>`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--></div></label></div></fieldset> <div class="space-y-2">`);
              Label($$renderer5, {
                for: "new-booklet-sheet",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Matching Answer Sheet`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              if (answer_sheets.length === 0) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-100">No compatible Answer Sheet is active. You can name this
                        draft now, but authoring and finalization remain
                        blocked.</p>`);
              } else if (answer_sheets.length === 1) {
                $$renderer5.push("<!--[1-->");
                $$renderer5.push(`<p class="bg-muted rounded-lg p-3 text-sm">${escape_html(answer_sheets[0].label)}</p>`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.select(
                  {
                    id: "new-booklet-sheet",
                    class: selectClass,
                    value: templateVersionId
                  },
                  ($$renderer6) => {
                    $$renderer6.option({ value: "" }, ($$renderer7) => {
                      $$renderer7.push(`Choose an Answer Sheet`);
                    });
                    $$renderer6.push(`<!--[-->`);
                    const each_array = ensure_array_like(answer_sheets);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let sheet = each_array[$$index];
                      $$renderer6.option({ value: sheet.id }, ($$renderer7) => {
                        $$renderer7.push(`${escape_html(sheet.label)}`);
                      });
                    }
                    $$renderer6.push(`<!--]-->`);
                  }
                );
              }
              $$renderer5.push(`<!--]--></div></div> `);
              Dialog_footer($$renderer5, {
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    onclick: createBooklet,
                    disabled: saving || !name.trim() || answer_sheets.length > 1 && !templateVersionId,
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->${escape_html(saving ? "Creating Booklet…" : "Create Booklet")}`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
        },
        $$slots: { default: true }
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
  NewBookletDialog as N
};
