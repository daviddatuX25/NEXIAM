import { e as escape_html, a as ensure_array_like, B as Button, d as derived, q as success } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
    let { draft_templates = [], draft_booklets = [], errors = {} } = $$props;
    let templateVersionId = draft_templates[0]?.id ?? null;
    let bookletVersionId = draft_booklets[0]?.id ?? null;
    let saving = false;
    const selectedTemplate = derived(() => draft_templates.find((template) => template.id === templateVersionId) ?? null);
    const selectedBooklet = derived(() => draft_booklets.find((booklet) => booklet.id === bookletVersionId) ?? null);
    const templateSummary = derived(() => selectedTemplate() ? `${selectedTemplate().page_count} pages · ${selectedTemplate().page_width}×${selectedTemplate().page_height} px` : "");
    const bookletSummary = derived(() => selectedBooklet() ? `${selectedBooklet().questions_count} questions` : "");
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam Materials", href: "/setup/exam-materials" },
      { label: "Activate Pair" }
    ];
    function submit() {
      if (!templateVersionId || !bookletVersionId || saving) return;
      saving = true;
      router.post(
        "/setup/omr/pairs",
        {
          template_version_id: templateVersionId,
          booklet_version_id: bookletVersionId
        },
        {
          onSuccess: () => {
            success("Pair activated.");
            router.visit("/setup/exam-materials");
          },
          onError: () => {
            saving = false;
          },
          onFinish: () => {
            saving = false;
          }
        }
      );
    }
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        PageHeaderBar($$renderer3, {
          title: "Activate Template–Booklet Pair",
          description: "Link an answer-sheet template with a question booklet and activate the pairing for test administration."
        });
        $$renderer3.push(`<!----> <div class="min-w-0 w-full max-w-5xl space-y-6 p-4 sm:p-6">`);
        if (errors.omr_pair) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-destructive/50 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm">${escape_html(errors.omr_pair)}</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (draft_templates.length === 0 || draft_booklets.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="rounded-xl border border-border bg-card p-6"><h2 class="text-lg font-semibold">Nothing to pair yet</h2> <p class="text-muted-foreground mt-2 text-sm">`);
          if (draft_templates.length === 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`No draft template versions are available. `);
            Link($$renderer3, {
              href: "/setup/exam-materials/answer-sheets",
              class: "text-primary font-medium underline underline-offset-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Open Answer Sheet Studio`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> to create one.`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (draft_booklets.length === 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`No draft booklet versions are available. `);
            Link($$renderer3, {
              href: "/setup/omr/booklets",
              class: "text-primary font-medium underline underline-offset-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Author booklets`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> to create one.`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] items-start"><div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h2 class="text-sm font-semibold text-foreground">Select Assets to Pair</h2> <p class="text-xs text-muted-foreground">Pair an answer-sheet template with a question booklet</p></div> <div class="space-y-2">`);
          Label($$renderer3, {
            for: "template_version_id",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Template`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          $$renderer3.select(
            {
              id: "template_version_id",
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
              value: templateVersionId
            },
            ($$renderer4) => {
              $$renderer4.push(`<!--[-->`);
              const each_array = ensure_array_like(draft_templates);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let template = each_array[$$index];
                $$renderer4.option({ value: template.id }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(template.label)} (${escape_html(template.code)})`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(` <p class="text-muted-foreground text-xs">${escape_html(templateSummary())}</p></div> <div class="space-y-2">`);
          Label($$renderer3, {
            for: "booklet_version_id",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Booklet`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          $$renderer3.select(
            {
              id: "booklet_version_id",
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
              value: bookletVersionId
            },
            ($$renderer4) => {
              $$renderer4.push(`<!--[-->`);
              const each_array_1 = ensure_array_like(draft_booklets);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let booklet = each_array_1[$$index_1];
                $$renderer4.option({ value: booklet.id }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(booklet.label)} (${escape_html(booklet.code)})`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(` <p class="text-muted-foreground text-xs">${escape_html(bookletSummary())}</p></div> <div class="flex gap-3 pt-2">`);
          Button($$renderer3, {
            onclick: submit,
            disabled: !templateVersionId || !bookletVersionId || saving,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(saving ? "Activating…" : "Activate pair")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            asChild: true,
            variant: "outline",
            children: ($$renderer4) => {
              Link($$renderer4, {
                href: "/setup/exam-materials",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div> <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h3 class="font-semibold text-sm text-foreground">Pairing Specifications</h3> <p class="text-xs text-muted-foreground">Asset compatibility overview</p></div> <div class="space-y-3 text-sm"><div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Selected Template</span> <span class="font-medium text-foreground text-right">${escape_html(selectedTemplate()?.code ?? "None")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Template Dimensions</span> <span class="font-medium text-foreground text-right">${escape_html(templateSummary() || "—")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Selected Booklet</span> <span class="font-medium text-foreground text-right">${escape_html(selectedBooklet()?.code ?? "None")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Question Scope</span> <span class="font-medium text-foreground text-right">${escape_html(bookletSummary() || "—")}</span></div></div> <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Activating a template–booklet pair registers this combination as available for live exam session material selection and OMR sheet generation.</div></div></div>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Create as default
};
