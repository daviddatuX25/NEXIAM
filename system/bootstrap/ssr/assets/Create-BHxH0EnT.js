import { h as head, e as escape_html, b as attr, a as ensure_array_like, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PasswordStrengthInput } from "./PasswordStrengthInput-DagPPumI.js";
import { I as Input } from "./input-BUZAZKIK.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
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
import "./eye-off-Bx_raPQb.js";
import "./eye-DyG-RiwW.js";
import "./check-DK45Y9SJ.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      roles = [],
      can_full_hr = false,
      default_office = "hr",
      support_role = null,
      available_programs = []
    } = $$props;
    let selectedRoles = [];
    let accountType = "permanent";
    let password = "";
    let passwordConfirmation = "";
    const form = useForm({
      name: "",
      email: "",
      password: "",
      password_confirmation: "",
      roles: [],
      account_type: "permanent",
      office: default_office,
      program_codes: []
    });
    const availableProgramCodes = derived(() => (available_programs ?? []).map((p) => p.code));
    const selectedAvailableProgramCount = derived(() => availableProgramCodes().filter((c) => form.program_codes.includes(c)).length);
    const allAvailableProgramsSelected = derived(() => availableProgramCodes().length > 0 && selectedAvailableProgramCount() === availableProgramCodes().length);
    const breadcrumbs = [
      { label: "Staff", href: "/staff/personnel" },
      { label: "Create" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1jvkt3f", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Create staff</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="space-y-6 max-w-5xl"><div><h1 class="text-2xl font-semibold tracking-tight">Create staff</h1> <p class="text-sm text-muted-foreground">Support staff are the natural role for each office. Test Administrator and Proctor are never granted here.</p></div> <form class="space-y-6"><div class="grid gap-6 lg:grid-cols-2 items-start"><div class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-xs"><h2 class="text-base font-semibold text-foreground border-b border-border pb-2">Account Credentials</h2> <div class="space-y-2"><label for="name" class="text-sm font-medium">Name</label> `);
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
          $$renderer4.push(`<!----> `);
          if (form.errors?.name) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.name)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2"><label for="email" class="text-sm font-medium">Email</label> `);
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
          $$renderer4.push(`<!----> `);
          if (form.errors?.email) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.email)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          PasswordStrengthInput($$renderer4, {
            errors: form.errors,
            label: "Password",
            get password() {
              return password;
            },
            set password($$value) {
              password = $$value;
              $$settled = false;
            },
            get passwordConfirmation() {
              return passwordConfirmation;
            },
            set passwordConfirmation($$value) {
              passwordConfirmation = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-xs"><h2 class="text-base font-semibold text-foreground border-b border-border pb-2">Office &amp; Role Assignment</h2> `);
          if (can_full_hr) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2"><label for="office" class="text-sm font-medium">Office</label> `);
            Input($$renderer4, {
              id: "office",
              required: true,
              get value() {
                return form.office;
              },
              set value($$value) {
                form.office = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (form.errors?.office) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.office)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2"><p class="text-sm font-medium">Account type</p> <div class="flex flex-wrap gap-4 pt-1"><label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="radio" name="account_type" value="permanent"${attr("checked", accountType === "permanent", true)} class="h-4 w-4 accent-primary"/> <span class="text-sm">Staff (pick role below)</span></label> <label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="radio" name="account_type" value="support"${attr("checked", accountType === "support", true)} class="h-4 w-4 accent-primary"/> <span class="text-sm">Support staff (role follows office)</span></label></div></div> `);
            {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-2"><p class="text-sm font-medium">Roles</p> <p class="text-xs text-muted-foreground">Select at least one approved role helper.</p> <div class="flex flex-wrap gap-4 pt-2"><!--[-->`);
              const each_array = ensure_array_like(roles);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let r = each_array[$$index];
                $$renderer4.push(`<label class="flex min-h-11 cursor-pointer items-center gap-2"><input type="checkbox"${attr("checked", selectedRoles.includes(r.name), true)} class="h-4 w-4 rounded border-input accent-primary"/> <span class="text-sm">${escape_html(r.display_name)}</span></label>`);
              }
              $$renderer4.push(`<!--]--></div> `);
              if (form.errors?.roles) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.roles)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (selectedRoles.includes("program_head") || form.office === "program") {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="space-y-2 pt-3 border-t border-border/60"><div class="flex items-center justify-between"><p class="text-sm font-medium">Assigned Programs (Jurisdiction)</p> `);
                if (available_programs.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="flex items-center gap-3 text-xs"><button type="button" class="text-primary hover:underline font-medium cursor-pointer">${escape_html(allAvailableProgramsSelected() ? "Deselect all" : `Select all (${available_programs.length})`)}</button> `);
                  if (selectedAvailableProgramCount() > 0) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<button type="button" class="text-muted-foreground hover:text-destructive hover:underline cursor-pointer">Clear</button>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <p class="text-xs text-muted-foreground">Select 1 program for a Department Chair, or multiple programs for a College Dean (e.g. BSIT + BSCS for CCIT Dean).</p> <div class="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-3"><!--[-->`);
                const each_array_1 = ensure_array_like(available_programs);
                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                  let prog = each_array_1[$$index_1];
                  $$renderer4.push(`<label class="flex items-center gap-2 rounded-lg border border-border/70 p-2 text-xs cursor-pointer hover:bg-muted/40 transition-colors"${attr("title", prog.name ?? prog.code)}><input type="checkbox"${attr("checked", form.program_codes.includes(prog.code), true)} class="h-3.5 w-3.5 rounded border-input accent-primary shrink-0"/> <div class="min-w-0 flex-1 truncate"><span class="font-medium text-foreground">${escape_html(prog.code)}</span> `);
                  if (prog.name) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<span class="text-muted-foreground block truncate text-[10px]">${escape_html(prog.name)}</span>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--></div></label>`);
                }
                $$renderer4.push(`<!--]--></div> `);
                if (form.errors?.program_codes) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.program_codes)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<input type="hidden"${attr("value", form.office)}/> <div class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">This account is created as <span class="font-medium text-foreground">${escape_html(support_role?.display_name ?? "office support staff")}</span> for the <span class="font-medium text-foreground">${escape_html(default_office)}</span> office.</div> `);
            if (form.errors?.roles) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.roles)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          }
          $$renderer4.push(`<!--]--></div></div> <div class="flex gap-2 pt-2">`);
          Button($$renderer4, {
            type: "submit",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Creating…" : "Create staff")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: "/staff/personnel",
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form></div>`);
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
  Create as default
};
