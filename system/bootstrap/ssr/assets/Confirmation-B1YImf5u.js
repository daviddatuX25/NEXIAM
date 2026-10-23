import { h as head, e as escape_html, B as Button, d as derived } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
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
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Confirmation($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const STORAGE_KEY = "nexiam.applicant_access_key";
    let { reference_code = null, access_key = null } = $$props;
    let savedNotice = "";
    let tourOpen = false;
    let keyVisible = false;
    const form = useForm({ access_key: access_key ?? "", birthdate: "" });
    const safetyContext = derived(() => {
      const hasKey = Boolean(access_key || form.access_key);
      const hasRef = Boolean(reference_code);
      const safetyKey = `${hasKey ? "key" : "nokey"}:${hasRef ? "ref" : "noref"}`;
      return {
        entityKey: reference_code ?? "confirmation",
        safetyKey,
        freshness: "current",
        hasAccessKey: hasKey,
        hasReferenceCode: hasRef
      };
    });
    function saveOnDevice() {
      if (!form.access_key) {
        savedNotice = "No access key is available on this page. Check your email or contact the Registrar.";
        return;
      }
      try {
        localStorage.setItem(STORAGE_KEY, form.access_key);
        savedNotice = "Access key saved on this device. You will still need your birthdate to sign in.";
      } catch {
        savedNotice = "This browser blocked saving. Copy the key somewhere safe instead.";
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1wy5sok", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Application submitted</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-lg space-y-6 px-6 py-16"><div class="space-y-2"><div class="flex flex-wrap items-center gap-2"><h1 class="text-3xl font-semibold tracking-tight">Application submitted</h1> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "applicant-access-key-safety",
            label: "Need help keeping your access key safe? Ask Kuya Xiam!",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----></div> <p class="text-muted-foreground">Save your applicant reference and access key. You need the key plus your birthdate to open the
        applicant portal — you do not need to wait for email.</p></div> `);
          if (reference_code) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-md border bg-muted/40 px-4 py-5"><p class="text-sm text-muted-foreground">Applicant reference</p> <p class="mt-1 font-mono text-2xl font-semibold tracking-wide">${escape_html(reference_code)}</p></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (access_key || form.access_key) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 px-4 py-5" data-tour="access-key-display"><p class="text-sm font-medium text-foreground">Your access key</p> <p class="text-sm text-muted-foreground">Short key for paper notes (format ABCD-EFGH). Hidden by default — show to copy, then save on this
          device or write it down. A copy is also emailed when delivery is enabled.</p> <div class="flex flex-wrap items-center gap-2">`);
            Input($$renderer4, {
              type: keyVisible ? "text" : "password",
              readonly: true,
              autocomplete: "off",
              class: "min-h-11 max-w-xs font-mono tracking-wider",
              get value() {
                return form.access_key;
              },
              set value($$value) {
                form.access_key = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              class: "min-h-11",
              onclick: () => keyVisible = !keyVisible,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(keyVisible ? "Hide" : "Show")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> `);
            Button($$renderer4, {
              "data-tour": "access-key-save",
              type: "button",
              variant: "secondary",
              class: "min-h-11",
              onclick: saveOnDevice,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Save on this device`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (savedNotice) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-muted-foreground" role="status">${escape_html(savedNotice)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<p class="rounded-md border px-4 py-3 text-sm text-muted-foreground">The access key was already shown after submit. If you lost it, check your email or ask the Registrar
        to reissue a key.</p>`);
          }
          $$renderer4.push(`<!--]--> <form data-tour="access-key-sign-in" class="space-y-4 rounded-md border px-4 py-5"><p class="text-sm font-medium text-foreground">Continue to applicant portal</p> <p class="text-sm text-muted-foreground">Confirm your birthdate (the same date you entered on the application) to sign in with your access key.</p> `);
          if (!access_key) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2">`);
            Label($$renderer4, {
              for: "access_key",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Access key`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "access_key",
              type: "password",
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
            $$renderer4.push(`<!----></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "birthdate",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Birthdate`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
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
          $$renderer4.push(`<!----> `);
          if (form.errors.birthdate) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.birthdate)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors.access_key) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.access_key)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-11 w-full sm:w-auto",
            disabled: form.processing || !form.access_key,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Signing in…" : "Go to applicant portal")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></form> <div class="space-y-2 text-sm leading-6 text-muted-foreground"><p class="font-medium text-foreground">Also prepare for Registrar verification</p> <ul class="list-disc space-y-1 pl-5"><li>Form 138 / TOR, Good Moral Certificate, 2×2 IDs, and PSA birth certificate</li></ul></div> <p class="text-sm text-muted-foreground">Returning later? `);
          Link($$renderer4, {
            href: "/applicant/login",
            class: "underline underline-offset-4",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Applicant sign in`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> · `);
          Link($$renderer4, {
            href: "/",
            class: "underline underline-offset-4",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Home`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></p></div> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-access-key-safety",
            context: safetyContext(),
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
  Confirmation as default
};
