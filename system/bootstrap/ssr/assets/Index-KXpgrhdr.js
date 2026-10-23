import { a as ensure_array_like, e as escape_html, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { templates = [], errors = {} } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam Materials", href: "/setup/exam-materials" },
      { label: "Answer-Sheet Templates" }
    ];
    function latestVersion(template) {
      return template.versions?.[0] ?? null;
    }
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        {
          let actions = function($$renderer4) {
            Button($$renderer4, {
              href: "/setup/exam-materials/answer-sheets",
              size: "sm",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->New answer sheet`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              href: "/setup/omr/intake",
              variant: "outline",
              size: "sm",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Register bundle`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
          };
          PageHeaderBar($$renderer3, {
            title: "Answer-Sheet Templates",
            description: "Optical mark recognition layout templates with immutable version histories for exam bubble sheets.",
            actions
          });
        }
        $$renderer3.push(`<!----> <section class="space-y-6 p-6"><div class="grid gap-4 sm:grid-cols-2"><!--[-->`);
        const each_array = ensure_array_like(templates);
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let template = each_array[$$index];
          const latest = latestVersion(template);
          const drafts = template.versions?.filter((v) => v.status === "draft") ?? [];
          const activeVer = template.versions?.find((v) => v.status === "active") ?? null;
          $$renderer3.push(`<div class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"><div><div class="flex items-start justify-between gap-2"><div class="flex items-center gap-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">`);
          File_text($$renderer3, { class: "size-5" });
          $$renderer3.push(`<!----></div> <div><h3 class="text-foreground text-sm font-semibold">${escape_html(template.name)}</h3> <p class="text-muted-foreground font-mono text-xs">${escape_html(template.code)}</p></div></div> `);
          if (activeVer) {
            $$renderer3.push("<!--[0-->");
            Badge($$renderer3, {
              variant: "default",
              class: "text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Active v${escape_html(activeVer.version)}`);
              },
              $$slots: { default: true }
            });
          } else if (drafts.length > 0) {
            $$renderer3.push("<!--[1-->");
            Badge($$renderer3, {
              variant: "secondary",
              class: "text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Draft v${escape_html(drafts[0].version)}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> <div class="border-border/60 bg-muted/20 text-muted-foreground mt-4 space-y-2 rounded-lg border p-3 text-xs"><div class="flex items-center justify-between"><span>Total versions:</span> <span class="text-foreground font-medium">${escape_html(template.versions?.length ?? 0)}</span></div> `);
          if (latest) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="flex items-center justify-between"><span>Latest sheet (v${escape_html(latest.version)}):</span> <span class="text-foreground font-medium">${escape_html(latest.page_count)} page${escape_html(latest.page_count === 1 ? "" : "s")} · ${escape_html(latest.page_width)}×${escape_html(latest.page_height)}</span></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></div> <div class="border-border/60 mt-5 flex items-center justify-between border-t pt-3.5">`);
          Button($$renderer3, {
            href: `/setup/omr/templates/${template.id}`,
            variant: "outline",
            size: "xs",
            children: ($$renderer4) => {
              Scan_line($$renderer4, { class: "mr-1 size-3" });
              $$renderer4.push(`<!----> Dry Run`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          if (drafts.length > 0) {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              href: "/setup/exam-materials/answer-sheets",
              size: "xs",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Edit draft v${escape_html(drafts[0].version)}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (templates.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border/80 col-span-2 rounded-xl border border-dashed p-10 text-center">`);
          File_text($$renderer3, { class: "text-muted-foreground mx-auto size-9" });
          $$renderer3.push(`<!----> <h3 class="text-foreground mt-2 text-sm font-semibold">No answer-sheet templates yet</h3> <p class="text-muted-foreground mt-1 text-xs">Design a bubble sheet in the visual builder or register
                        an existing native bundle.</p> <div class="mt-4 flex items-center justify-center gap-2">`);
          Button($$renderer3, {
            href: "/setup/exam-materials/answer-sheets",
            size: "sm",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Create answer sheet`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            href: "/setup/omr/intake",
            variant: "outline",
            size: "sm",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Register bundle`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></section>`);
      }
    });
  });
}
export {
  Index as default
};
