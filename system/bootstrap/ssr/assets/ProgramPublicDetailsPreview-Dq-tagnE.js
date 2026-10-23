import { h as head, e as escape_html, b as attr, a as ensure_array_like, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
function ProgramPublicDetailsPreview($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { program, profile, revision = 0, published_revision = null } = $$props;
    const editorUrl = `/setup/programs/${Number(program.id)}/public-details`;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Public program details", href: editorUrl },
      { label: "Private draft preview" }
    ];
    head("uo8mbo", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Private program profile preview · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="mx-auto min-w-0 max-w-7xl space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Link($$renderer4, {
              href: editorUrl,
              children: ($$renderer5) => {
                Button($$renderer5, {
                  variant: "outline",
                  class: "min-h-11",
                  disabled: false,
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Back to editor`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Private program profile preview",
            description: "Review the saved profile privately. Visitors continue to see the last explicitly published revision.",
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="border-border bg-card/70 rounded-lg border p-4 text-sm" role="note">`);
        if (published_revision) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`Visitors currently see published revision ${escape_html(published_revision)}. This preview shows saved profile revision ${escape_html(revision)} only to authorized program staff.`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`No profile revision has been published. This preview is private; the public program page does not show this draft.`);
        }
        $$renderer3.push(`<!--]--></div> `);
        if (Number(revision) === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="border-border bg-card/70 rounded-lg border p-6" aria-labelledby="empty-preview-title"><h2 id="empty-preview-title" class="text-lg font-semibold">No saved draft yet</h2> <p class="text-muted-foreground mt-2 text-sm leading-6">Save a profile draft in the editor before opening its private preview.</p></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<article class="border-border bg-card/70 min-w-0 overflow-hidden rounded-lg border">`);
          if (profile.photos?.[0]) {
            $$renderer3.push("<!--[0-->");
            if (profile.photos[0].available === false) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="bg-muted/50 text-muted-foreground p-6 text-center text-sm">The private cover image is unavailable. Remove or replace it in the editor before publishing.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<figure class="bg-muted aspect-[16/7] overflow-hidden"><img${attr("src", profile.photos[0].src)}${attr("alt", profile.photos[0].alt_text)} class="size-full object-cover"/></figure>`);
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <div class="space-y-6 p-5 sm:p-8"><header class="space-y-3"><p class="font-mono text-xs font-semibold tracking-wide text-muted-foreground">${escape_html(program.code)}</p> <h2 class="text-2xl font-semibold leading-tight">${escape_html(program.name)}</h2> `);
          if (profile.short_description) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="max-w-3xl text-base leading-7 text-muted-foreground">${escape_html(profile.short_description)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></header> <div class="max-w-4xl whitespace-pre-wrap break-words text-sm leading-7">${escape_html(profile.description)}</div> `);
          if (profile.photos?.length > 1) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<section class="space-y-4" aria-labelledby="private-preview-gallery-title"><h3 id="private-preview-gallery-title" class="text-lg font-semibold">Saved photo gallery</h3> <ul class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
            const each_array = ensure_array_like(profile.photos.slice(1));
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let photo = each_array[$$index];
              $$renderer3.push(`<li class="border-border min-w-0 overflow-hidden rounded-lg border">`);
              if (photo.available === false) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="bg-muted/50 text-muted-foreground p-4 text-sm">This private photo is unavailable.</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<img${attr("src", photo.src)}${attr("alt", photo.alt_text)} class="aspect-[4/3] w-full object-cover"/>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (photo.caption) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="p-3 text-sm text-muted-foreground">${escape_html(photo.caption)}</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></li>`);
            }
            $$renderer3.push(`<!--]--></ul></section>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></article>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  ProgramPublicDetailsPreview as default
};
