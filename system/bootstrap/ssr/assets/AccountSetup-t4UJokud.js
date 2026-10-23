import { h as head, e as escape_html, b as attr, a as ensure_array_like, c as attr_class, B as Button, d as derived } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { P as Pin_input } from "./pin-input-NkY2XT0X.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { C as Clipboard } from "./clipboard-BXuTlHdz.js";
import { D as Download } from "./download-n32NlL8c.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
import { C as Copy } from "./copy-D9_YDhto.js";
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
function AccountSetup($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      staff = {},
      purpose = "activation",
      two_factor_required = false,
      password_set = false,
      two_factor_confirmed = false
    } = $$props;
    let step = purpose === "mfa_reset" ? 2 : purpose === "mfa_enrollment" && password_set ? 2 : password_set ? two_factor_confirmed ? 3 : 2 : 1;
    let password = "";
    let confirmation = "";
    let setup = null;
    let code = "";
    let totpFormElement = null;
    let codes = [];
    let copied = false;
    let enrolling = false;
    let error = "";
    const criteria = derived(() => [
      { label: "At least 8 characters", met: password.length >= 8 },
      {
        label: "Uppercase and lowercase letters",
        met: /[A-Z]/.test(password) && /[a-z]/.test(password)
      },
      { label: "At least one number", met: /\d/.test(password) },
      {
        label: "At least one symbol",
        met: /[^A-Za-z0-9]/.test(password)
      }
    ]);
    const passwordForm = useForm({ password: "", password_confirmation: "" });
    async function postJson(url, body = {}) {
      const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? ""
        },
        body: JSON.stringify(body)
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? "The request could not be completed.");
      return data;
    }
    async function beginEnrollment() {
      if (enrolling) return;
      enrolling = true;
      error = "";
      try {
        setup = await postJson("/staff/account-setup/two-factor");
        step = 2;
      } catch (caught) {
        console.error("[NEXIAM Setup Error]", caught);
        error = caught.message || "Failed to initialize authenticator QR code.";
      } finally {
        enrolling = false;
      }
    }
    async function copyCodes() {
      await navigator.clipboard.writeText(codes.join("\n"));
      copied = true;
    }
    function downloadCodes() {
      const blob = new Blob(
        [
          `NEXIAM staff recovery codes
Save these codes somewhere safe. Each code works once.

${codes.join("\n")}
`
        ],
        { type: "text/plain" }
      );
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = "nexiam-staff-recovery-codes.txt";
      anchor.click();
      URL.revokeObjectURL(url);
    }
    function completeSetup() {
      router.post("/staff/account-setup/complete");
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1acnags", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Staff account setup</title>`);
        });
      });
      GuestLayout($$renderer3, {
        maxWidth: "max-w-xl",
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
                      $$renderer6.push(`<div class="flex items-center gap-3"><div class="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary">`);
                      Shield_check($$renderer6, { class: "size-6" });
                      $$renderer6.push(`<!----></div> <div>`);
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-xl sm:text-2xl font-bold",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(purpose === "mfa_reset" ? "Reconfigure Multi-Factor Authentication" : "Secure Your Staff Account")}`);
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
                            if (purpose === "mfa_reset") {
                              $$renderer7.push("<!--[0-->");
                              $$renderer7.push(`Your previous authenticator was reset. Scan this code on your new device.`);
                            } else {
                              $$renderer7.push("<!--[-1-->");
                              $$renderer7.push(`${escape_html(staff.name)} · <span class="font-mono text-xs">${escape_html(staff.email)}</span>`);
                            }
                            $$renderer7.push(`<!--]-->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div></div> <div class="mt-5 grid grid-cols-4 gap-2" role="group"${attr("aria-label", `Setup progress: Step ${step} of 4`)}><!--[-->`);
                      const each_array = ensure_array_like(["Password", "Authenticator", "Recovery", "Finish"]);
                      for (let index = 0, $$length = each_array.length; index < $$length; index++) {
                        let label = each_array[index];
                        $$renderer6.push(`<div class="space-y-1"><div${attr_class(`h-1.5 rounded-full transition-colors ${step >= index + 1 ? "bg-primary" : "bg-muted"}`)}></div> <p${attr_class(`text-center text-[11px] ${step >= index + 1 ? "font-medium text-foreground" : "text-muted-foreground"}`)}>${escape_html(label)}</p></div>`);
                      }
                      $$renderer6.push(`<!--]--></div>`);
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
                      if (error) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive" role="alert">${escape_html(error)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (step === 1) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<form class="space-y-4"><div><h2 class="text-lg font-semibold">Choose a new password</h2> <p class="text-sm text-muted-foreground">Use a unique password you do not use for any other account.</p></div> <div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "new-password",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->New password`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Input($$renderer6, {
                          id: "new-password",
                          type: "password",
                          autocomplete: "new-password",
                          required: true,
                          minlength: "8",
                          "aria-invalid": !!passwordForm.errors.password,
                          "aria-describedby": passwordForm.errors.password ? "new-password-error" : void 0,
                          get value() {
                            return password;
                          },
                          set value($$value) {
                            password = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----> `);
                        if (passwordForm.errors.password) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p id="new-password-error" class="text-sm text-destructive" role="alert">${escape_html(passwordForm.errors.password)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> <ul class="grid gap-1 sm:grid-cols-2" aria-label="Password strength checks"><!--[-->`);
                        const each_array_1 = ensure_array_like(criteria());
                        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                          let criterion = each_array_1[$$index_1];
                          $$renderer6.push(`<li${attr_class(`flex items-center gap-2 text-xs ${criterion.met ? "text-emerald-700 dark:text-emerald-400 font-medium" : "text-muted-foreground"}`)}>`);
                          Check($$renderer6, { class: "size-3.5" });
                          $$renderer6.push(`<!----> ${escape_html(criterion.label)}</li>`);
                        }
                        $$renderer6.push(`<!--]--></ul> <div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "confirm-password",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Confirm password`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Input($$renderer6, {
                          id: "confirm-password",
                          type: "password",
                          autocomplete: "new-password",
                          required: true,
                          minlength: "8",
                          "aria-invalid": Boolean(confirmation && confirmation !== password),
                          "aria-describedby": confirmation && confirmation !== password ? "confirm-password-error" : void 0,
                          get value() {
                            return confirmation;
                          },
                          set value($$value) {
                            confirmation = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----> `);
                        if (confirmation && confirmation !== password) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p id="confirm-password-error" class="text-xs text-destructive" role="alert">Passwords do not match.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> `);
                        Button($$renderer6, {
                          class: "w-full",
                          type: "submit",
                          disabled: passwordForm.processing || password.length < 8 || confirmation !== password,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(passwordForm.processing ? "Saving…" : "Save password")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></form>`);
                      } else if (step === 2) {
                        $$renderer6.push("<!--[1-->");
                        $$renderer6.push(`<div class="space-y-4"><div><h2 class="text-lg font-semibold">Set up your authenticator</h2> <p class="text-sm text-muted-foreground">Scan this QR code with your authenticator app (Google Authenticator, Microsoft Authenticator, or 1Password), then enter its 6-digit code.</p></div> `);
                        if (setup) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex flex-col items-center justify-center p-3 rounded-xl border border-border/60 bg-white shadow-xs"><img${attr("src", setup.qr_svg)} alt="Authenticator enrollment QR code" class="size-56"/></div> <div class="rounded-lg border border-border/60 bg-muted/30 p-3 space-y-2"><div class="flex items-center justify-between"><span class="text-xs font-medium text-muted-foreground">Can't scan the QR code?</span> <button type="button" class="inline-flex items-center gap-1 text-xs text-primary hover:underline">`);
                          {
                            $$renderer6.push("<!--[-1-->");
                            Copy($$renderer6, { class: "size-3" });
                            $$renderer6.push(`<!----> Copy secret key`);
                          }
                          $$renderer6.push(`<!--]--></button></div> <p class="break-all font-mono text-xs text-foreground bg-background p-2 rounded border border-border/40 select-all">${escape_html(setup.secret)}</p></div> <form class="space-y-3 pt-2">`);
                          Label($$renderer6, {
                            for: "totp-code",
                            class: "text-sm font-medium",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Six-digit verification code`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Pin_input($$renderer6, {
                            inputId: "totp-code",
                            onComplete: () => totpFormElement?.requestSubmit(),
                            class: "justify-center",
                            get value() {
                              return code;
                            },
                            set value($$value) {
                              code = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----> `);
                          Button($$renderer6, {
                            type: "submit",
                            class: "w-full",
                            disabled: code.length !== 6,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Verify authenticator`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></form>`);
                        } else if (enrolling) {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<div class="py-12 text-center text-sm text-muted-foreground space-y-2"><div class="mx-auto size-6 animate-spin rounded-full border-2 border-primary border-r-transparent"></div> <p>Preparing authenticator QR code…</p></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-center space-y-3"><p class="text-sm font-semibold text-destructive">${escape_html(error || "Authenticator setup could not be loaded.")}</p> <p class="text-xs text-muted-foreground">The setup session may have expired or is awaiting verification.</p> `);
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            size: "sm",
                            onclick: beginEnrollment,
                            class: "gap-1.5",
                            children: ($$renderer7) => {
                              Refresh_cw($$renderer7, { class: "size-3.5" });
                              $$renderer7.push(`<!----> Retry setup`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div>`);
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (!two_factor_required) {
                          $$renderer6.push("<!--[0-->");
                          Button($$renderer6, {
                            variant: "outline",
                            class: "w-full",
                            onclick: () => step = 3,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Skip optional setup`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div>`);
                      } else if (step === 3) {
                        $$renderer6.push("<!--[2-->");
                        $$renderer6.push(`<div class="space-y-4"><div><h2 class="text-lg font-semibold">Save your recovery codes</h2> <p class="text-sm text-muted-foreground">Each code works once as a fallback if you lose your phone or authenticator app. Keep them in a safe place.</p></div> `);
                        if (codes.length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="grid grid-cols-1 gap-2 rounded-lg border bg-muted/40 p-4 font-mono text-sm sm:grid-cols-2"><!--[-->`);
                          const each_array_2 = ensure_array_like(codes);
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let recoveryCode = each_array_2[$$index_2];
                            $$renderer6.push(`<code class="p-1 rounded bg-background/80 text-center font-bold tracking-wider">${escape_html(recoveryCode)}</code>`);
                          }
                          $$renderer6.push(`<!--]--></div> <div class="flex flex-wrap gap-2">`);
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            onclick: copyCodes,
                            class: "gap-1.5",
                            children: ($$renderer7) => {
                              Clipboard($$renderer7, { class: "size-4" });
                              $$renderer7.push(`<!----> ${escape_html(copied ? "Copied to clipboard" : "Copy codes")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            onclick: downloadCodes,
                            class: "gap-1.5",
                            children: ($$renderer7) => {
                              Download($$renderer7, { class: "size-4" });
                              $$renderer7.push(`<!----> Download as TXT`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div>`);
                        } else if (two_factor_confirmed) {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<div class="rounded-md border p-4"><p class="text-sm">Recovery codes were shown only once. If you did not save them, contact an HR administrator to reset your authenticator.</p></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-md border p-4"><p class="text-sm">Authenticator enrollment is optional for your account role. You can proceed without it.</p></div>`);
                        }
                        $$renderer6.push(`<!--]--> `);
                        Button($$renderer6, {
                          class: "w-full",
                          onclick: () => step = 4,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(codes.length ? "I have safely saved my recovery codes" : "Continue")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<div class="space-y-4 text-center py-4"><div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">`);
                        Check($$renderer6, { class: "size-7" });
                        $$renderer6.push(`<!----></div> <h2 class="text-xl font-bold">You're ready to go!</h2> <p class="text-sm text-muted-foreground max-w-sm mx-auto">Your staff account credentials and multi-factor security have been configured. You can now access campus governance.</p> `);
                        Button($$renderer6, {
                          class: "w-full",
                          onclick: completeSetup,
                          children: ($$renderer7) => {
                            Key_round($$renderer7, { class: "size-4 mr-1.5" });
                            $$renderer7.push(`<!----> Enter Staff Portal`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                      }
                      $$renderer6.push(`<!--]-->`);
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
  AccountSetup as default
};
