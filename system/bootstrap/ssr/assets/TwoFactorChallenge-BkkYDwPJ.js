import { h as head, e as escape_html, b as attr, B as Button, d as derived } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import "clsx";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { P as Pin_input } from "./pin-input-NkY2XT0X.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./x-CnxrMbMn.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./Link-B7ks7rBK.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./watch.svelte-BcPoqckX.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./noop-BQS7OFs8.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function TwoFactorChallenge($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const form = useForm({ code: "", recovery_code: "" });
    let formElement = null;
    const activeField = derived(() => "code");
    const activeError = derived(() => form.errors[activeField()]);
    function cancel() {
      router.delete("/two-factor-challenge");
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("bfd57k", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Two-Factor Authentication</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              class: "rounded-lg shadow-xl backdrop-blur-xl",
              style: "font-family: 'IBM Plex Sans', sans-serif",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-2xl",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Two-Factor Authentication`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Enter the 6-digit code from your authenticator app`);
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
                    children: ($$renderer6) => {
                      $$renderer6.push(`<form class="space-y-5">`);
                      {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "code",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Authentication code`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Pin_input($$renderer6, {
                          inputId: "code",
                          disabled: form.processing,
                          autofocus: true,
                          onComplete: () => formElement?.requestSubmit(),
                          class: "justify-center",
                          get value() {
                            return form.code;
                          },
                          set value($$value) {
                            form.code = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----></div>`);
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (activeError()) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p id="challenge-error" class="text-sm text-destructive" role="alert">${escape_html(activeError())}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <button type="button" class="text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4"${attr("aria-label", "Use a recovery code instead")}>${escape_html("Use a recovery code instead")}</button> <div class="flex flex-col gap-2.5">`);
                      Button($$renderer6, {
                        type: "submit",
                        class: "w-full",
                        disabled: form.processing,
                        "aria-busy": form.processing,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(form.processing ? "Verifying…" : "Verify and continue")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        type: "button",
                        variant: "outline",
                        class: "w-full",
                        disabled: form.processing,
                        onclick: cancel,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Cancel`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div></form>`);
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
  TwoFactorChallenge as default
};
