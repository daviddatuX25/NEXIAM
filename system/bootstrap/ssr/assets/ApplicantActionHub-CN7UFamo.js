import { h as head, e as escape_html, a as ensure_array_like } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import "axios";
import "@inertiajs/core";
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
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
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
import "./shield-alert-CXtDdte9.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function ApplicantActionHub($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      attempt = { id: null, reference_code: "" },
      destinations = []
    } = $$props;
    head("17u492d", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Applicant Action Hub</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs: [{ label: "Applicant Action Hub" }],
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        if (page_state !== "ready") {
          $$renderer3.push("<!--[0-->");
          E2PageState($$renderer3, { state: page_state });
        } else {
          $$renderer3.push("<!--[-1-->");
          PageHeaderBar($$renderer3, {
            title: "Applicant Action Hub",
            description: "Authorized workflow actions and quick destinations available for your current office role and assigned scope."
          });
          $$renderer3.push(`<!----> `);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              children: ($$renderer4) => {
                if (Card_header) {
                  $$renderer4.push("<!--[-->");
                  Card_header($$renderer4, {
                    class: "pb-3",
                    children: ($$renderer5) => {
                      if (Card_title) {
                        $$renderer5.push("<!--[-->");
                        Card_title($$renderer5, {
                          class: "text-lg",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Applicant`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      if (Card_description) {
                        $$renderer5.push("<!--[-->");
                        Card_description($$renderer5, {
                          class: "font-mono text-xs",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(attempt.reference_code || "No reference")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "pt-4",
                    children: ($$renderer5) => {
                      if (destinations.length === 0) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="flex items-center gap-2 text-sm text-muted-foreground">`);
                        Inbox($$renderer5, { class: "size-4" });
                        $$renderer5.push(`<!----> No action is available for your current duty or scope.</div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        $$renderer5.push(`<ul class="divide-y"><!--[-->`);
                        const each_array = ensure_array_like(destinations);
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let destination = each_array[$$index];
                          $$renderer5.push(`<li>`);
                          Link($$renderer5, {
                            href: destination.url,
                            class: "flex items-center justify-between gap-3 py-3 text-sm font-medium hover:text-primary",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->${escape_html(destination.label)} `);
                              Arrow_right($$renderer6, { class: "size-4 shrink-0 text-muted-foreground" });
                              $$renderer6.push(`<!---->`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push(`<!----></li>`);
                        }
                        $$renderer5.push(`<!--]--></ul>`);
                      }
                      $$renderer5.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` <p class="text-xs text-muted-foreground">Scanning never executes any of these actions automatically.</p>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  ApplicantActionHub as default
};
