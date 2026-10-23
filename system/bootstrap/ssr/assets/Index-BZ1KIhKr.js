import { h as head, B as Button, a as ensure_array_like, e as escape_html } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pause } from "./pause-DNvJ1Xre.js";
import { P as Play } from "./play-DnSz4pBE.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { templates = [] } = $$props;
    let deleteTarget = null;
    const paperLabels = { a4: "A4", legal: "Legal", letter: "Letter" };
    const unitLabels = {
      full: "Full page",
      half_a4: "Half A4",
      half_legal: "Half Legal",
      half_letter: "Half Letter"
    };
    function confirmDelete(template) {
      deleteTarget = template;
    }
    function closeDelete() {
      deleteTarget = null;
    }
    function doDelete() {
      if (!deleteTarget) return;
      router.delete(`/setup/result-templates/${deleteTarget.id}`, { onSuccess: () => deleteTarget = null });
    }
    function activate(t) {
      router.post(`/setup/result-templates/${t.id}/activate`);
    }
    function deactivate(t) {
      router.post(`/setup/result-templates/${t.id}/deactivate`);
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Result Sheet Templates" }
    ];
    head("hkliwv", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Result Sheet Templates · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Link($$renderer4, {
              href: "/setup/result-templates/create",
              children: ($$renderer5) => {
                Button($$renderer5, {
                  class: "min-h-10 gap-2",
                  children: ($$renderer6) => {
                    Plus($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> New template`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Result Sheet Templates",
            description: "Configure institutional score reports and official result sheet templates for print and distribution.",
            actions
          });
        }
        $$renderer3.push(`<!----> `);
        if (templates.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"><div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl">`);
          File_text($$renderer3, { class: "text-muted-foreground size-6" });
          $$renderer3.push(`<!----></div> <h3 class="text-foreground mt-4 text-base font-semibold">No Result Sheet templates yet</h3> <p class="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">Create an HTML visual studio template or upload a validated
                    Word (.docx) template to drive official result sheet output.</p> <div class="mt-5 flex justify-center">`);
          Link($$renderer3, {
            href: "/setup/result-templates/create",
            children: ($$renderer4) => {
              Button($$renderer4, {
                class: "min-h-10 gap-2",
                children: ($$renderer5) => {
                  Plus($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> New template`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<section class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border" aria-label="Result sheet templates"><!--[-->`);
          const each_array = ensure_array_like(templates);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let t = each_array[$$index];
            $$renderer3.push(`<article class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
            MaterialDocumentPreview($$renderer3, {
              src: t.thumbnail_url,
              kind: "result_sheet",
              mode: t.mode?.toUpperCase() ?? "HTML",
              title: t.name,
              alt: `${t.name} template preview`
            });
            $$renderer3.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-center gap-2"><h2 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(t.name)}</h2> `);
            if (t.is_active) {
              $$renderer3.push("<!--[0-->");
              Badge($$renderer3, {
                variant: "default",
                class: "bg-emerald-600 text-white hover:bg-emerald-500 gap-1",
                children: ($$renderer4) => {
                  $$renderer4.push(`<span class="size-1.5 rounded-full bg-white"></span> Active`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
              Badge($$renderer3, {
                variant: "secondary",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Inactive`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer3.push(`<!--]--> `);
            Badge($$renderer3, {
              variant: "outline",
              class: "font-mono text-[10px] tracking-wider uppercase",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(t.mode === "docx" ? "Word (docx)" : "HTML Studio")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <p class="text-foreground/80 mt-1.5 text-sm font-medium">${escape_html(paperLabels[t.paper_size] ?? t.paper_size)} · ${escape_html(t.orientation === "landscape" ? "Landscape" : "Portrait")} · ${escape_html(unitLabels[t.logical_unit] ?? t.logical_unit ?? "Full page")}</p> <p class="text-muted-foreground mt-1 text-xs">`);
            if (t.watermark_text) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`Watermark: <span class="font-mono text-foreground">${escape_html(t.watermark_text)}</span> ·`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (t.updated_at) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`Updated ${escape_html(new Date(t.updated_at).toLocaleDateString())}`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></p></div> <div class="flex flex-wrap items-center gap-2 sm:self-center">`);
            if (t.is_active) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                variant: "outline",
                size: "sm",
                class: "h-9 gap-1.5 px-3 text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-400",
                onclick: () => deactivate(t),
                children: ($$renderer4) => {
                  Pause($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----> Deactivate`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
              Button($$renderer3, {
                variant: "outline",
                size: "sm",
                class: "h-9 gap-1.5 px-3 text-xs font-semibold text-primary hover:text-primary",
                onclick: () => activate(t),
                children: ($$renderer4) => {
                  Play($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----> Activate`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer3.push(`<!--]--> `);
            Link($$renderer3, {
              href: `/setup/result-templates/${t.id}/edit`,
              children: ($$renderer4) => {
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  class: "h-9 gap-1.5 px-3 text-xs font-semibold",
                  children: ($$renderer5) => {
                    Pencil($$renderer5, { class: "size-3.5" });
                    $$renderer5.push(`<!----> Edit`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            Button($$renderer3, {
              variant: "ghost",
              size: "sm",
              class: "text-destructive hover:bg-destructive/10 h-9 gap-1.5 px-2.5 text-xs font-semibold",
              onclick: () => confirmDelete(t),
              title: "Delete template",
              children: ($$renderer4) => {
                Trash_2($$renderer4, { class: "size-3.5" });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div></article>`);
          }
          $$renderer3.push(`<!--]--></section>`);
        }
        $$renderer3.push(`<!--]--></div> `);
        if (deleteTarget) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"><div class="bg-card w-full max-w-sm rounded-lg p-6 shadow-lg"><h2 class="text-base font-semibold">Delete template</h2> <p class="text-muted-foreground mt-2 text-sm">Delete “${escape_html(deleteTarget.name)}”? Printed sheets already
                    generated are not affected.</p> <div class="mt-4 flex justify-end gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            onclick: closeDelete,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            variant: "destructive",
            onclick: doDelete,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Delete`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
    });
  });
}
export {
  Index as default
};
