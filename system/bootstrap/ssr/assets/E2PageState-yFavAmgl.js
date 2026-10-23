import { e as escape_html } from "../app.js";
import "clsx";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { I as Info } from "./info-C3MCzlQW.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function E2PageState($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { state = "ready" } = $$props;
    if (state === "ready") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="sr-only" data-testid="page-state">Page state: <span class="text-foreground">${escape_html(state)}</span></p>`);
    } else if (state === "denied") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="my-4" data-testid="page-state">`);
      if (Card) {
        $$renderer2.push("<!--[-->");
        Card($$renderer2, {
          class: "border-destructive/30 bg-destructive/5",
          children: ($$renderer3) => {
            if (Card_header) {
              $$renderer3.push("<!--[-->");
              Card_header($$renderer3, {
                class: "pb-2",
                children: ($$renderer4) => {
                  $$renderer4.push(`<div class="flex items-center gap-2 text-destructive">`);
                  Shield_alert($$renderer4, { class: "size-5" });
                  $$renderer4.push(`<!----> `);
                  if (Card_title) {
                    $$renderer4.push("<!--[-->");
                    Card_title($$renderer4, {
                      class: "text-base font-semibold",
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->Access Restricted`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer4.push("<!--]-->");
                  } else {
                    $$renderer4.push("<!--[!-->");
                    $$renderer4.push("<!--]-->");
                  }
                  $$renderer4.push(`</div> `);
                  if (Card_description) {
                    $$renderer4.push("<!--[-->");
                    Card_description($$renderer4, {
                      class: "text-xs",
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->Your current actor role or duty does not have permission to view or manage this workspace.`);
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
          },
          $$slots: { default: true }
        });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
      $$renderer2.push(`</div>`);
    } else if (state === "empty") {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<div class="my-4" data-testid="page-state">`);
      if (Card) {
        $$renderer2.push("<!--[-->");
        Card($$renderer2, {
          children: ($$renderer3) => {
            if (Card_content) {
              $$renderer3.push("<!--[-->");
              Card_content($$renderer3, {
                class: "flex flex-col items-center justify-center p-8 text-center text-muted-foreground",
                children: ($$renderer4) => {
                  Inbox($$renderer4, { class: "mb-2 size-8 text-muted-foreground/60" });
                  $$renderer4.push(`<!----> <p class="text-sm font-medium text-foreground">No records available</p> <p class="mt-1 text-xs text-muted-foreground">There are no records currently available for this section.</p>`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
      $$renderer2.push(`</div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="my-4" data-testid="page-state">`);
      if (Card) {
        $$renderer2.push("<!--[-->");
        Card($$renderer2, {
          children: ($$renderer3) => {
            if (Card_content) {
              $$renderer3.push("<!--[-->");
              Card_content($$renderer3, {
                class: "flex items-center gap-3 p-4 text-sm text-muted-foreground",
                children: ($$renderer4) => {
                  Info($$renderer4, { class: "size-5 shrink-0 text-muted-foreground" });
                  $$renderer4.push(`<!----> <div><span class="font-medium capitalize text-foreground">${escape_html(state.replace(/_/g, " "))}</span> <p class="mt-0.5 text-xs text-muted-foreground">This section is currently in state: ${escape_html(state)}.</p></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
      $$renderer2.push(`</div>`);
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  E2PageState as default
};
