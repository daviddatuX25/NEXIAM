import { e as escape_html, B as Button, a as ensure_array_like, v as error, q as success, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
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
import "./badge-D9Vgs7xh.js";
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
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { draft = null, areas = [], capabilities = {}, errors = {} } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam Materials", href: "/setup/exam-materials" },
      { label: "Author Booklet" }
    ];
    let code = draft?.code ?? "";
    let name = draft?.name ?? "";
    let questions = (draft?.questions ?? []).map((q) => ({
      area_id: q.area_id,
      correct_option_code: q.correct_option_code ?? "A"
    }));
    let bulk = "";
    let saving = false;
    const canSubmit = derived(() => Boolean(code.trim()) && Boolean(name.trim()) && (questions.length > 0 || bulk.trim() !== "") && questions.every((q) => q.area_id));
    function addQuestion() {
      questions = [
        ...questions,
        { area_id: areas[0]?.id ?? null, correct_option_code: "A" }
      ];
    }
    function removeQuestion(index) {
      questions = questions.filter((_, i) => i !== index);
    }
    function submit() {
      if (!canSubmit() || saving) return;
      saving = true;
      const bulkText = bulk.trim();
      router.post(
        "/setup/omr/booklets",
        {
          code: code.trim(),
          name: name.trim(),
          questions: bulkText === "" ? questions.map((q, i) => ({
            area_id: q.area_id,
            position: i + 1,
            correct_option_code: q.correct_option_code
          })) : [],
          bulk: bulkText
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            success(`Answer booklet ${code.trim()} saved.`);
            router.visit("/setup/omr/booklets");
          },
          onError: (errs) => {
            error(errs.omr_booklet ?? Object.values(errs)[0] ?? "The answer booklet could not be saved.");
          },
          onFinish: () => {
            saving = false;
          }
        }
      );
    }
    const selectClass = "h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30";
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          PageHeaderBar($$renderer4, {
            title: "Author Booklet",
            description: "Configure booklet identification, subject sections, and the official answer key for exam scoring."
          });
          $$renderer4.push(`<!----> <section class="space-y-6 p-6">`);
          if (errors.omr_booklet) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded bg-red-50 p-3 text-sm text-red-800">${escape_html(errors.omr_booklet)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="max-w-4xl rounded-lg border p-4"><div class="grid max-w-2xl gap-4 sm:grid-cols-2"><div class="space-y-2">`);
          Label($$renderer4, {
            for: "booklet_code",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "booklet_code",
            placeholder: "e.g. BKLT-2026-01",
            class: "uppercase",
            "aria-invalid": Boolean(errors.omr_booklet),
            get value() {
              return code;
            },
            set value($$value) {
              code = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "booklet_name",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Name`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "booklet_name",
            placeholder: "e.g. Entrance Exam Booklet A",
            get value() {
              return name;
            },
            set value($$value) {
              name = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div></div> `);
          if (draft) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground mt-3 text-xs">Editing a draft copied from an existing version — saving
                    creates a new booklet version.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="mt-5"><div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold">Answer key (${escape_html(questions.length)} question${escape_html(questions.length === 1 ? "" : "s")})</h3> `);
          Button($$renderer4, {
            size: "sm",
            variant: "outline",
            onclick: addQuestion,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Add question`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <table class="w-full text-sm"><thead><tr class="text-muted-foreground text-left"><th class="py-1">Position</th><th>Area</th><th>Correct option</th><th class="text-right"></th></tr></thead><tbody><!--[-->`);
          const each_array = ensure_array_like(questions);
          for (let i = 0, $$length = each_array.length; i < $$length; i++) {
            let question = each_array[i];
            $$renderer4.push(`<tr class="border-t"><td class="w-20 py-1 align-middle font-mono">${escape_html(i + 1)}</td><td class="py-1 pr-2 align-middle">`);
            $$renderer4.select(
              {
                class: selectClass,
                value: question.area_id ?? "",
                onchange: (e) => {
                  question.area_id = Number(e.currentTarget.value) || null;
                }
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "", disabled: true }, ($$renderer6) => {
                  $$renderer6.push(`Select area…`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_1 = ensure_array_like(areas);
                for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                  let area = each_array_1[$$index];
                  $$renderer5.option({ value: area.id }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(area.code)} — ${escape_html(area.name)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</td><td class="w-32 py-1 align-middle">`);
            $$renderer4.select(
              {
                class: selectClass,
                value: question.correct_option_code,
                onchange: (e) => {
                  question.correct_option_code = e.currentTarget.value;
                }
              },
              ($$renderer5) => {
                $$renderer5.push(`<!--[-->`);
                const each_array_2 = ensure_array_like(["A", "B", "C", "D"]);
                for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                  let option = each_array_2[$$index_1];
                  $$renderer5.option({ value: option }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(option)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</td><td class="w-24 py-1 text-right align-middle">`);
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              onclick: () => removeQuestion(i),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Remove`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></td></tr>`);
          }
          $$renderer4.push(`<!--]--></tbody></table> `);
          if (questions.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground mt-2 text-sm">Add questions above or paste a bulk key below.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="mt-5 space-y-2">`);
          Label($$renderer4, {
            for: "booklet_bulk",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Or paste the key (position area_code option per line, #
                    comments)`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Textarea($$renderer4, {
            id: "booklet_bulk",
            rows: "6",
            class: "font-mono text-xs",
            placeholder: "1 MATH01 A\n2 MATH01 C\n# science section\n10 SCI02 B",
            get value() {
              return bulk;
            },
            set value($$value) {
              bulk = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p class="text-muted-foreground text-xs">Bulk rows replace the table.</p></div> <div class="mt-5 flex gap-3">`);
          Button($$renderer4, {
            onclick: submit,
            disabled: !canSubmit() || saving,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(saving ? "Saving…" : "Save booklet")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></div></section>`);
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
  Create as default
};
