import { u as usePage, h as head, e as escape_html, B as Button, d as derived } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_footer } from "./card-footer-Dl7GHCIG.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { E as Eye_off } from "./eye-off-Bx_raPQb.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
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
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./Link-B7ks7rBK.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./clone-BJz-lxsY.js";
import "./hidden-input-GSZDfM3n.js";
import "./check-DK45Y9SJ.js";
import "./minus-Dqkhcxt7.js";
import "es-toolkit";
import "es-toolkit/compat";
function Login($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const page = usePage();
    const demo = page.props.demo ?? null;
    const form = useForm({
      email: demo?.email ?? "",
      password: demo?.password ?? "",
      remember: false
    });
    const errors = derived(() => form.errors ?? page.props.errors ?? {});
    let showPassword = false;
    let passwordInput = null;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("ckjewl", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Staff login</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              class: "border border-border/60 shadow-xl backdrop-blur-xl",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">`);
                      Shield_check($$renderer6, { class: "size-4 text-primary", "aria-hidden": "true" });
                      $$renderer6.push(`<!----> <span>Institutional Staff Access</span></div> `);
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-2xl font-bold tracking-tight",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Staff Portal`);
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
                            $$renderer7.push(`<!---->Authorized examination administration, grading, and admissions governance`);
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
                      {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <a href="/auth/google/redirect" class="flex items-center justify-center gap-3 w-full h-11 rounded-lg border border-border/60 bg-background hover:bg-muted/60 transition-colors text-sm font-semibold shadow-xs"><svg class="size-5" viewBox="0 0 48 48" role="img" aria-label="Google"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 37.98 46.98 31.85 46.98 24.55Z"></path><path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.88.93 7.56 2.56 10.78l7.97-6.19Z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.9 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z"></path></svg> Continue with Google</a> <div class="relative my-4"><div class="absolute inset-0 flex items-center"><span class="w-full border-t border-border/60"></span></div> <div class="relative flex justify-center text-xs uppercase"><span class="bg-card px-2 text-muted-foreground font-medium">Or continue with password</span></div></div> <form class="space-y-4"><div class="space-y-2"><div class="flex items-center justify-between">`);
                      Label($$renderer6, {
                        for: "email",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Email`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      Input($$renderer6, {
                        id: "email",
                        type: "email",
                        autocomplete: "username",
                        required: true,
                        "aria-invalid": !!errors()?.email,
                        "aria-describedby": errors()?.email ? "email-error" : void 0,
                        get value() {
                          return form.email;
                        },
                        set value($$value) {
                          form.email = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (errors()?.email) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p id="email-error" class="text-sm text-destructive">${escape_html(errors().email)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "password",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Password`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> <div class="relative">`);
                      Input($$renderer6, {
                        id: "password",
                        type: showPassword ? "text" : "password",
                        autocomplete: "current-password",
                        required: true,
                        "aria-invalid": !!errors()?.password,
                        "aria-describedby": errors()?.password ? "password-error" : void 0,
                        class: "pr-10",
                        get ref() {
                          return passwordInput;
                        },
                        set ref($$value) {
                          passwordInput = $$value;
                          $$settled = false;
                        },
                        get value() {
                          return form.password;
                        },
                        set value($$value) {
                          form.password = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        type: "button",
                        variant: "ghost",
                        size: "icon",
                        class: "absolute right-1 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground",
                        "aria-label": showPassword ? "Hide password" : "Show password",
                        "aria-pressed": showPassword,
                        onclick: () => showPassword = !showPassword,
                        children: ($$renderer7) => {
                          if (showPassword) {
                            $$renderer7.push("<!--[0-->");
                            Eye_off($$renderer7, { class: "size-4", "aria-hidden": "true" });
                          } else {
                            $$renderer7.push("<!--[-1-->");
                            Eye($$renderer7, { class: "size-4", "aria-hidden": "true" });
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (errors()?.password) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p id="password-error" class="text-sm text-destructive">${escape_html(errors().password)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <label class="flex items-center gap-2 text-sm text-muted-foreground">`);
                      Checkbox($$renderer6, {
                        get checked() {
                          return form.remember;
                        },
                        set checked($$value) {
                          form.remember = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> Remember me</label> `);
                      Button($$renderer6, {
                        type: "submit",
                        class: "w-full",
                        disabled: form.processing,
                        "aria-busy": form.processing,
                        children: ($$renderer7) => {
                          if (form.processing) {
                            $$renderer7.push("<!--[0-->");
                            $$renderer7.push(`<span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span> Signing in…`);
                          } else {
                            $$renderer7.push("<!--[-1-->");
                            Lock($$renderer7, { class: "size-4", "aria-hidden": "true" });
                            $$renderer7.push(`<!----> Sign in`);
                          }
                          $$renderer7.push(`<!--]-->`);
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
                $$renderer5.push(` `);
                if (Card_footer) {
                  $$renderer5.push("<!--[-->");
                  Card_footer($$renderer5, {
                    class: "block",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="mt-6 pt-4 border-t border-border/40 text-center space-y-2"><p class="text-xs text-muted-foreground">Applicant checking examination status? <a href="/applicant/login" class="text-primary font-medium hover:underline inline-flex items-center gap-1">Go to Applicant Portal `);
                      Arrow_right($$renderer6, { class: "size-3", "aria-hidden": "true" });
                      $$renderer6.push(`<!----></a></p> <p class="text-xs text-muted-foreground">Have an activation voucher? <a href="/staff/activate" class="text-primary hover:underline">Activate account</a></p></div>`);
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
  Login as default
};
