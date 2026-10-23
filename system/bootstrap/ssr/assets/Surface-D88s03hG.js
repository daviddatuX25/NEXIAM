import { h as head, e as escape_html, B as Button, b as attr, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pause } from "./pause-DNvJ1Xre.js";
import { P as Play } from "./play-DnSz4pBE.js";
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
function Surface($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { surface, templates = [] } = $$props;
    let deleteTarget = null;
    const breadcrumbs = derived(() => [
      { label: "Setup", href: "/setup" },
      { label: "Report Templates", href: "/setup/report-templates" },
      { label: surface.title }
    ]);
    function activate(template) {
      router.post(`/setup/report-templates/${surface.surface_key}/${template.id}/activate`);
    }
    function deactivate(template) {
      router.post(`/setup/report-templates/${surface.surface_key}/${template.id}/deactivate`);
    }
    function deleteTemplate() {
      if (!deleteTarget) return;
      router.delete(`/setup/report-templates/${surface.surface_key}/${deleteTarget.id}`, { onSuccess: () => deleteTarget = null });
    }
    head("3awre6", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(surface.title)} Templates · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs: breadcrumbs(),
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div class="space-y-2"><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight text-foreground">${escape_html(surface.title)}</h1> `);
        Badge($$renderer3, {
          variant: "outline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(surface.office_label)}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="max-w-2xl text-sm text-muted-foreground">One active template drives every ${escape_html(surface.title)} export. Create and maintain office-approved report layouts here.</p></div> `);
        Link($$renderer3, {
          href: `/setup/report-templates/${surface.surface_key}/create`,
          children: ($$renderer4) => {
            Button($$renderer4, {
              class: "min-h-10 gap-2",
              children: ($$renderer5) => {
                Plus($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> New Template`);
              },
              $$slots: { default: true }
            });
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></header> `);
        if (templates.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="rounded-xl border border-dashed border-border bg-card px-6 py-12 text-center"><h2 class="text-base font-semibold text-foreground">No templates yet</h2> <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">Create an HTML template or upload a DOCX file to configure ${escape_html(surface.title)} exports.</p> <div class="mt-5">`);
          Link($$renderer3, {
            href: `/setup/report-templates/${surface.surface_key}/create`,
            children: ($$renderer4) => {
              Button($$renderer4, {
                class: "min-h-10 gap-2",
                children: ($$renderer5) => {
                  Plus($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> New Template`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<section class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"${attr("aria-label", `${surface.title} templates`)}><!--[-->`);
          const each_array = ensure_array_like(templates);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let tpl = each_array[$$index];
            $$renderer3.push(`<article class="grid gap-4 p-4 transition-colors hover:bg-muted/25 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
            MaterialDocumentPreview($$renderer3, {
              src: tpl.thumbnail_url,
              kind: "report",
              mode: tpl.mode?.toUpperCase() ?? "HTML",
              title: tpl.name,
              builder_json: tpl.builder_json,
              orientation: tpl.orientation ?? "portrait",
              alt: `${tpl.name} template preview`
            });
            $$renderer3.push(`<!----> <div class="min-w-0 space-y-2"><div class="flex flex-wrap items-center gap-2"><h2 class="text-base font-semibold text-foreground">${escape_html(tpl.name)}</h2> `);
            if (tpl.is_active) {
              $$renderer3.push("<!--[0-->");
              Badge($$renderer3, {
                class: "bg-emerald-600 text-white hover:bg-emerald-600",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Active`);
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
              class: "font-mono text-[10px] uppercase",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(tpl.mode === "docx" ? "DOCX" : "HTML")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <p class="text-sm font-medium text-foreground/80">${escape_html(String(tpl.paper_size ?? "").toUpperCase())} · ${escape_html(tpl.orientation === "landscape" ? "Landscape" : "Portrait")}</p></div> <div class="flex flex-wrap items-center gap-2">`);
            if (tpl.is_active) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                variant: "outline",
                size: "sm",
                class: "h-9 gap-1.5 px-3 text-xs font-semibold",
                onclick: () => deactivate(tpl),
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
                class: "h-9 gap-1.5 px-3 text-xs font-semibold",
                onclick: () => activate(tpl),
                children: ($$renderer4) => {
                  Play($$renderer4, { class: "size-3.5" });
                  $$renderer4.push(`<!----> Activate`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer3.push(`<!--]--> `);
            Link($$renderer3, {
              href: `/setup/report-templates/${surface.surface_key}/${tpl.id}/edit`,
              children: ($$renderer4) => {
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  class: "h-9 px-3 text-xs font-semibold",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Edit`);
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
              onclick: () => deleteTarget = tpl,
              "aria-label": `Delete ${tpl.name}`,
              children: ($$renderer4) => {
                Trash_2($$renderer4, { class: "size-3.5" });
                $$renderer4.push(`<!----> Delete`);
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
          $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-report-template-title"><div class="w-full max-w-sm rounded-lg bg-card p-6 shadow-lg"><h2 id="delete-report-template-title" class="text-base font-semibold">Delete template?</h2> <p class="mt-2 text-sm text-muted-foreground">Delete “${escape_html(deleteTarget.name)}”? This action cannot be undone.</p> <div class="mt-4 flex justify-end gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            onclick: () => deleteTarget = null,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            variant: "destructive",
            onclick: deleteTemplate,
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
  Surface as default
};
