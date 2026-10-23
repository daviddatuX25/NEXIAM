import { h as head, e as escape_html, a as ensure_array_like, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { F as Folder_open } from "./folder-open-BF8o-1TF.js";
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
import "./card-content-Brg2wRkV.js";
import "./card-description-BGy_v5-d.js";
import "./card-title-BNY2ZPje.js";
import "./shield-alert-CXtDdte9.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "denied", programs = [], cycle = null } = $$props;
    const showUrl = (code) => typeof route === "function" ? route("program-head.show", { program: code }) : `/staff/programs/${code}`;
    const breadcrumbs = [
      { label: "Program head", href: "/staff/programs" },
      { label: "My programs" }
    ];
    const programHeadContext = derived(() => {
      const safetyKey = `${page_state}:${programs?.length ?? 0}:${cycle ?? "none"}`;
      return {
        entityKey: "program-head-hub",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        programsCount: programs?.length ?? 0,
        cycle: cycle ?? null,
        firstProgramCode: programs?.[0]?.code ?? null
      };
    });
    head("1m7tg9s", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>My Programs</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6" data-tour="program-head-assigned-programs">`);
        if (page_state !== "ready") {
          $$renderer3.push("<!--[0-->");
          E2PageState($$renderer3, { state: page_state });
        } else {
          $$renderer3.push("<!--[-1-->");
          PageHeaderBar($$renderer3, {
            title: "My Programs",
            description: "Review applicant pipelines, qualification cohorts, and enrollment quotas for your assigned academic programs.",
            tourKey: "program-head-overview",
            context: programHeadContext()
          });
          $$renderer3.push(`<!----> `);
          if (cycle) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-sm text-muted-foreground">Active cycle: <span class="font-mono text-xs font-semibold text-foreground">${escape_html(cycle)}</span></p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (programs.length === 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-muted-foreground py-12 text-center">No programs are assigned to you yet. Contact the registrar to scope your workspace.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><!--[-->`);
            const each_array = ensure_array_like(programs);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let program = each_array[$$index];
              $$renderer3.push(`<li class="glass-panel flex flex-col rounded-2xl p-5 transition-shadow hover:shadow-md" data-tour="program-head-program-card"><p class="font-mono text-xs font-semibold uppercase tracking-wider text-muted-foreground">${escape_html(program.code)}</p> <h2 class="mt-1 text-lg font-semibold text-foreground">${escape_html(program.name)}</h2> <div class="mt-4 flex flex-wrap gap-2">`);
              Link($$renderer3, {
                href: showUrl(program.code),
                children: ($$renderer4) => {
                  Button($$renderer4, {
                    class: "min-h-10 gap-2",
                    "data-tour": "program-head-open-program",
                    children: ($$renderer5) => {
                      Folder_open($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----> Open program`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----> `);
              if (program.public_details_href) {
                $$renderer3.push("<!--[0-->");
                Link($$renderer3, {
                  href: program.public_details_href,
                  children: ($$renderer4) => {
                    Button($$renderer4, {
                      variant: "outline",
                      class: "min-h-10",
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->Edit public details`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Index as default
};
