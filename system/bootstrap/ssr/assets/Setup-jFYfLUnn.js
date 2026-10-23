import { h as head, B as Button } from "../app.js";
import { G as GuestLayout } from "./GuestLayout-CiSx7FHc.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
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
import "./Link-B7ks7rBK.js";
import "./card-content-Brg2wRkV.js";
import "./card-description-BGy_v5-d.js";
import "./card-title-BNY2ZPje.js";
import "./shield-alert-CXtDdte9.js";
import "./inbox-AmUVtgA2.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Setup($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready" } = $$props;
    const form = useForm({
      token: "",
      email: "",
      password: "",
      password_confirmation: "",
      name: ""
    });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1bzx5pt", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Applicant setup</title>`);
        });
      });
      GuestLayout($$renderer3, {
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-md space-y-4 p-6">`);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----> <form class="space-y-3"><div>`);
          Label($$renderer4, {
            for: "token",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Setup token`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
          Input($$renderer4, {
            id: "token",
            required: true,
            get value() {
              return form.token;
            },
            set value($$value) {
              form.token = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div>`);
          Label($$renderer4, {
            for: "name",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Display name`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
          Input($$renderer4, {
            id: "name",
            required: true,
            get value() {
              return form.name;
            },
            set value($$value) {
              form.name = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div>`);
          Label($$renderer4, {
            for: "email",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Login email`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
          Input($$renderer4, {
            id: "email",
            type: "email",
            required: true,
            get value() {
              return form.email;
            },
            set value($$value) {
              form.email = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div>`);
          Label($$renderer4, {
            for: "password",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Password`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
          Input($$renderer4, {
            id: "password",
            type: "password",
            required: true,
            get value() {
              return form.password;
            },
            set value($$value) {
              form.password = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div>`);
          Label($$renderer4, {
            for: "password_confirmation",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Confirm`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
          Input($$renderer4, {
            id: "password_confirmation",
            type: "password",
            required: true,
            get value() {
              return form.password_confirmation;
            },
            set value($$value) {
              form.password_confirmation = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> `);
          Button($$renderer4, {
            type: "submit",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Complete setup`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></form></div>`);
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
  Setup as default
};
