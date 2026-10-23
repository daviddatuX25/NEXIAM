import { h as head, e as escape_html, B as Button } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_footer } from "./card-footer-Dl7GHCIG.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { T as Ticket_check } from "./ticket-check-j0koCZml.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
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
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Activate($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const form = useForm({ reference: "", fallback: "", token: "" });
    let fragmentLoaded = false;
    function readFragment() {
      if (typeof window === "undefined" || fragmentLoaded) return;
      fragmentLoaded = true;
      const hash = window.location.hash.startsWith("#") ? window.location.hash.slice(1) : window.location.hash;
      const params = new URLSearchParams(hash);
      form.reference = (params.get("v") ?? "").toUpperCase();
      form.token = params.get("t") ?? "";
      if (params.has("v") || params.has("t")) {
        window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
      }
    }
    readFragment();
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("supwnf", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Activate staff account</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          Card($$renderer4, {
            variant: "glass",
            class: "border border-border/60 shadow-xl backdrop-blur-xl",
            children: ($$renderer5) => {
              Card_header($$renderer5, {
                class: "text-center",
                children: ($$renderer6) => {
                  $$renderer6.push(`<div class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">`);
                  Ticket_check($$renderer6, { class: "size-6" });
                  $$renderer6.push(`<!----></div> `);
                  Card_title($$renderer6, {
                    class: "text-2xl font-bold",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Activate Staff Account`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Card_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Enter your voucher reference and 8-character fallback code from your physical slip, or open the link you received.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Card_content($$renderer5, {
                children: ($$renderer6) => {
                  $$renderer6.push(`<form class="space-y-4"><div class="space-y-2">`);
                  Label($$renderer6, {
                    for: "voucher-reference",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Voucher reference`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "voucher-reference",
                    placeholder: "XXXXXXXXXX",
                    required: true,
                    maxlength: "10",
                    autocomplete: "off",
                    class: "font-mono uppercase tracking-wider text-center",
                    "aria-invalid": !!form.errors.reference,
                    "aria-describedby": form.errors.reference ? "ref-error" : void 0,
                    get value() {
                      return form.reference;
                    },
                    set value($$value) {
                      form.reference = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> `);
                  if (form.errors.reference) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p id="ref-error" class="text-sm text-destructive" role="alert">${escape_html(form.errors.reference)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                  Label($$renderer6, {
                    for: "voucher-fallback",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->8-character fallback code`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> <div class="relative">`);
                  Key_round($$renderer6, {
                    class: "absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "voucher-fallback",
                    placeholder: "XXXX-XXXX",
                    maxlength: "9",
                    autocomplete: "one-time-code",
                    class: "pl-9 font-mono uppercase tracking-widest text-center",
                    "aria-invalid": !!form.errors.fallback,
                    "aria-describedby": form.errors.fallback ? "fallback-error" : void 0,
                    get value() {
                      return form.fallback;
                    },
                    set value($$value) {
                      form.fallback = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----></div> `);
                  if (form.errors.fallback) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p id="fallback-error" class="text-sm text-destructive" role="alert">${escape_html(form.errors.fallback)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> `);
                  if (form.errors.token) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(form.errors.token)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--> `);
                  Button($$renderer6, {
                    class: "w-full",
                    type: "submit",
                    disabled: form.processing,
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->${escape_html(form.processing ? "Verifying…" : "Continue securely")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----></form>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Card_footer($$renderer5, {
                class: "block",
                children: ($$renderer6) => {
                  $$renderer6.push(`<div class="pt-2 text-center"><p class="text-xs text-muted-foreground">Single-use onboarding credential · Expires 24 hours after issuance</p></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            },
            $$slots: { default: true }
          });
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
  Activate as default
};
