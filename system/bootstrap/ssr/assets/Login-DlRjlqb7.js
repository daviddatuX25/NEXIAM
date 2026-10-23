import { h as head, B as Button, e as escape_html } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Login($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", demo = null } = $$props;
    let keyVisible = false;
    const form = useForm({ access_key: "", birthdate: "" });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("o1ugbp", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Applicant sign in</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-md px-6 py-12">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">`);
                      Key_round($$renderer6, { class: "size-3.5 text-primary", "aria-hidden": "true" });
                      $$renderer6.push(`<!----> <span>Admissions Gateway</span></div> `);
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-2xl font-bold tracking-tight",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Applicant Sign In`);
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
                            $$renderer7.push(`<!---->Enter your 8-character access key (ABCD-EFGH) and birthdate to view your application, examination schedule, and results.`);
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
                      $$renderer6.push(`<form class="space-y-4"><div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "access_key",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Access key`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> <div class="flex gap-2">`);
                      Input($$renderer6, {
                        id: "access_key",
                        type: keyVisible ? "text" : "password",
                        required: true,
                        autocomplete: "off",
                        class: "min-h-11 font-mono tracking-wider",
                        placeholder: "ABCD-EFGH",
                        get value() {
                          return form.access_key;
                        },
                        set value($$value) {
                          form.access_key = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        type: "button",
                        variant: "outline",
                        class: "min-h-11 shrink-0",
                        onclick: () => keyVisible = !keyVisible,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(keyVisible ? "Hide" : "Show")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (form.errors.access_key) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(form.errors.access_key)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "birthdate",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Birthdate`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "birthdate",
                        type: "date",
                        required: true,
                        class: "min-h-11",
                        get value() {
                          return form.birthdate;
                        },
                        set value($$value) {
                          form.birthdate = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (form.errors.birthdate) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(form.errors.birthdate)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      Button($$renderer6, {
                        type: "submit",
                        class: "min-h-11 w-full",
                        disabled: form.processing,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(form.processing ? "Signing in…" : "Sign in")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></form>`);
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
          $$renderer4.push(` <p class="mt-6 text-center text-sm text-muted-foreground">Lost your key? Contact the Registrar’s office to request a reissue. <br/> `);
          Link($$renderer4, {
            href: "/apply",
            class: "underline underline-offset-4",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Apply`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> · `);
          Link($$renderer4, {
            href: "/login",
            class: "underline underline-offset-4",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Staff sign in`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></p></div>`);
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
  Login as default
};
