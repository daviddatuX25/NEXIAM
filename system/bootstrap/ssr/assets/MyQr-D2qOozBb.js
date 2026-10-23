import { h as head, e as escape_html, B as Button, d as derived } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import "qrcode";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./dialog-description-D12L0QA9.js";
import "./dialog-footer-kNnwKT2h.js";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./chevron-right-BHu-PGOg.js";
import "./file-text-PXEtmXm9.js";
import "./shield-DTtuUihb.js";
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
import "./card-description-BGy_v5-d.js";
import "./shield-alert-CXtDdte9.js";
function MyQr($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", attempt_id = null, qr = "" } = $$props;
    let tourOpen = false;
    const qrContext = derived(() => {
      const hasQ = Boolean(qr && qr.trim().length > 0);
      const safetyKey = `${page_state}:${attempt_id ?? "noatt"}:${hasQ ? "qr" : "noqr"}`;
      return {
        entityKey: attempt_id ? `attempt-${attempt_id}` : "my-qr",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        hasQr: hasQ,
        attemptId: attempt_id ? Number(attempt_id) : null
      };
    });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1k2n0vf", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>My NEXIAM QR</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "My NEXIAM QR",
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-lg space-y-5">`);
          if (page_state !== "ready") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="sr-only">`);
            E2PageState($$renderer4, { state: page_state });
            $$renderer4.push(`<!----></div> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-6 text-center",
                      children: ($$renderer6) => {
                        Shield_check($$renderer6, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">QR unavailable</p> <p class="text-sm text-muted-foreground">This account cannot access the applicant QR.</p>`);
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
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="space-y-1"><div class="flex items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight">My NEXIAM QR</h1> `);
            TourTriggerGlyph($$renderer4, {
              tourKey: "applicant-qr",
              label: "Need tips on using your QR pass? Ask Kuya Xiam!",
              onclick: () => tourOpen = true
            });
            $$renderer4.push(`<!----></div> `);
            if (attempt_id) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="font-mono text-sm text-muted-foreground">Attempt #${escape_html(attempt_id)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden shadow-sm",
                "data-tour": "qr-card",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "flex flex-col items-center gap-4 p-6",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="rounded-xl border bg-white p-3"><canvas class="block size-60" role="img" aria-label="Your applicant QR code"></canvas></div> <div class="space-y-1 text-center"><p class="text-sm font-medium">Show this QR when asked by NEXIAM staff</p> <p class="text-xs text-muted-foreground">One permanent code for your admission attempt — it never changes or expires.</p></div> `);
                        Button($$renderer6, {
                          variant: "outline",
                          class: "min-h-11",
                          onclick: () => window.print(),
                          children: ($$renderer7) => {
                            Printer($$renderer7, { class: "size-4" });
                            $$renderer7.push(`<!----> Print approved copy`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
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
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "pb-3",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->About this QR`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 pt-2 text-sm",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<ul class="list-disc space-y-1.5 pl-5 text-muted-foreground"><li>It identifies your admission attempt — nothing else.</li> <li>It contains no name, scores, program, action, or permission.</li> <li>Permanent — the same code is valid for your whole admission window.</li> <li>Use it at the consultation front desk, registrar counter, or with any staff scan.</li></ul> <p class="flex gap-2 text-xs text-muted-foreground">`);
                        Shield_check($$renderer6, { class: "mt-0.5 size-3.5 shrink-0" });
                        $$renderer6.push(`<!----> Printing is recommended; the printed copy is the same permanent code.</p>`);
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
          }
          $$renderer4.push(`<!--]--></div> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-qr",
            context: qrContext(),
            get open() {
              return tourOpen;
            },
            set open($$value) {
              tourOpen = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!---->`);
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
  MyQr as default
};
