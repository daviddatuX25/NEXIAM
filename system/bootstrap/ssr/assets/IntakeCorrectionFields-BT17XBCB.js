import { e as escape_html, a as ensure_array_like, s as stringify, d as derived } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { P as PhilippineAddressSelector } from "./PhilippineAddressSelector-UGmHumOi.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./create-id-CUJmDlea.js";
import "style-to-object";
function IntakeCorrectionFields($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      form,
      programs = [],
      grade_fields = [],
      identity = null,
      identityEditable = false,
      showInternalNotes = false,
      showResubmissionNotes = false,
      idPrefix = "intake"
    } = $$props;
    const optionsFor2 = derived(() => programs.filter((p) => p.code !== form.program_preference_1_code));
    const optionsFor3 = derived(() => programs.filter((p) => p.code !== form.program_preference_1_code && p.code !== form.program_preference_2_code));
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="space-y-6"><section class="space-y-3"><h3 class="text-sm font-medium">Personal information</h3> <p class="text-xs text-muted-foreground">`);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`Legal name and identity fields are editable only on this authorized post-accept path.
        Status stays accepted — exam assignment is not cancelled.`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`Legal name, birthdate, and sex are locked to the applicant identity record. Quiet correction
        cannot change them — use Authorize amendment after accept if needed.`);
      }
      $$renderer3.push(`<!--]--></p> <div class="grid gap-3 sm:grid-cols-2"><div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-first_name`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->First name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-first_name`,
          class: "min-h-11",
          get value() {
            return form.identity_first_name;
          },
          set value($$value) {
            form.identity_first_name = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-first_name`,
          value: identity?.first_name ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-last_name`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Last name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-last_name`,
          class: "min-h-11",
          get value() {
            return form.identity_last_name;
          },
          set value($$value) {
            form.identity_last_name = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-last_name`,
          value: identity?.last_name ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-middle_name`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Middle name <span class="text-muted-foreground">(optional)</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-middle_name`,
          class: "min-h-11",
          get value() {
            return form.identity_middle_name;
          },
          set value($$value) {
            form.identity_middle_name = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-middle_name`,
          value: identity?.middle_name ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-suffix`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Suffix <span class="text-muted-foreground">(optional)</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-suffix`,
          class: "min-h-11",
          get value() {
            return form.identity_suffix;
          },
          set value($$value) {
            form.identity_suffix = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-suffix`,
          value: identity?.suffix ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-identity-email`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Email (identity)`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-identity-email`,
          type: "email",
          class: "min-h-11",
          get value() {
            return form.identity_email;
          },
          set value($$value) {
            form.identity_email = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-identity-email`,
          type: "email",
          value: identity?.email ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-birthdate`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Birthdate`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        Input($$renderer3, {
          id: `${idPrefix}-birthdate`,
          type: "date",
          class: "min-h-11",
          get value() {
            return form.identity_birthdate;
          },
          set value($$value) {
            form.identity_birthdate = $$value;
            $$settled = false;
          }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-birthdate`,
          type: "date",
          value: identity?.birthdate ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-sex`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Sex`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (identityEditable) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.select(
          {
            id: `${idPrefix}-sex`,
            value: form.identity_sex,
            class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "male" }, ($$renderer5) => {
              $$renderer5.push(`Male`);
            });
            $$renderer4.option({ value: "female" }, ($$renderer5) => {
              $$renderer5.push(`Female`);
            });
          }
        );
      } else {
        $$renderer3.push("<!--[-1-->");
        Input($$renderer3, {
          id: `${idPrefix}-sex`,
          value: identity?.sex === "female" ? "Female" : identity?.sex === "male" ? "Male" : identity?.sex ?? "",
          disabled: true,
          class: "min-h-11"
        });
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-applicant_type`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Applicant type`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      $$renderer3.select(
        {
          id: `${idPrefix}-applicant_type`,
          value: form.applicant_type,
          class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        },
        ($$renderer4) => {
          $$renderer4.option({ value: "new" }, ($$renderer5) => {
            $$renderer5.push(`New`);
          });
          $$renderer4.option({ value: "transferee" }, ($$renderer5) => {
            $$renderer5.push(`Transferee`);
          });
        }
      );
      $$renderer3.push(` `);
      if (form.errors?.applicant_type) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-sm text-destructive">${escape_html(form.errors.applicant_type)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div></section> <section class="space-y-3"><h3 class="text-sm font-medium">Academic background</h3> <p class="text-xs text-muted-foreground">Digital grades are a consultation snapshot. Hard-copy Form 138 / TOR is still required at the
      Registrar.</p> <div class="grid gap-3 sm:grid-cols-2"><div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: `${idPrefix}-last_school`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Last school enrolled`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: `${idPrefix}-last_school`,
        class: "min-h-11",
        placeholder: "School of origin",
        get value() {
          return form.last_school_enrolled;
        },
        set value($$value) {
          form.last_school_enrolled = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-strand`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(form.applicant_type === "transferee" ? "Previous course" : "SHS strand")}`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: `${idPrefix}-strand`,
        class: "min-h-11",
        get value() {
          return form.strand;
        },
        set value($$value) {
          form.strand = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <!--[-->`);
      const each_array = ensure_array_like(grade_fields);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let field = each_array[$$index];
        $$renderer3.push(`<div class="space-y-1.5">`);
        Label($$renderer3, {
          for: `${idPrefix}-grade_${field.code}`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(field.label)}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Input($$renderer3, {
          id: `${idPrefix}-grade_${field.code}`,
          type: "number",
          step: "0.01",
          min: field.min_value,
          max: field.max_value,
          class: "min-h-11",
          get value() {
            return form.grades[field.code];
          },
          set value($$value) {
            form.grades[field.code] = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> `);
        if (form.errors?.[`grades.${field.code}`]) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-destructive">${escape_html(form.errors[`grades.${field.code}`])}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
      $$renderer3.push(`<!--]--></div></section> <section class="space-y-3"><h3 class="text-sm font-medium">Contact information <span class="font-normal text-muted-foreground">(optional)</span></h3> <div class="grid gap-3 sm:grid-cols-2"><div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: `${idPrefix}-contact_email`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Email`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: `${idPrefix}-contact_email`,
        type: "email",
        class: "min-h-11",
        get value() {
          return form.contact_email;
        },
        set value($$value) {
          form.contact_email = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors?.contact_email) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-sm text-destructive">${escape_html(form.errors.contact_email)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-phone`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Phone`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: `${idPrefix}-phone`,
        type: "tel",
        class: "min-h-11",
        get value() {
          return form.contact_phone;
        },
        set value($$value) {
          form.contact_phone = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div class="sm:col-span-2">`);
      PhilippineAddressSelector($$renderer3, {
        idPrefix: `${stringify(idPrefix)}-ph-addr`,
        errors: form.errors,
        get address_line() {
          return form.address_line;
        },
        set address_line($$value) {
          form.address_line = $$value;
          $$settled = false;
        },
        get city() {
          return form.city;
        },
        set city($$value) {
          form.city = $$value;
          $$settled = false;
        },
        get province() {
          return form.province;
        },
        set province($$value) {
          form.province = $$value;
          $$settled = false;
        },
        get zip_code() {
          return form.zip_code;
        },
        set zip_code($$value) {
          form.zip_code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div></div></section> <section class="space-y-3"><h3 class="text-sm font-medium">Program preferences</h3> <p class="text-xs text-muted-foreground">Choose up to three distinct programs. First preference is required on apply; keep at least one
      when correcting.</p> <div class="space-y-3"><div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-pref1`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->First preference`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      $$renderer3.select(
        {
          id: `${idPrefix}-pref1`,
          value: form.program_preference_1_code,
          class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        },
        ($$renderer4) => {
          $$renderer4.option({ value: "" }, ($$renderer5) => {
            $$renderer5.push(`Select a program`);
          });
          $$renderer4.push(`<!--[-->`);
          const each_array_1 = ensure_array_like(programs);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let program = each_array_1[$$index_1];
            $$renderer4.option({ value: program.code }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
            });
          }
          $$renderer4.push(`<!--]-->`);
        }
      );
      $$renderer3.push(` `);
      if (form.errors?.program_preference_1_code) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-sm text-destructive">${escape_html(form.errors.program_preference_1_code)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-pref2`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Second preference <span class="text-muted-foreground">(optional)</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      $$renderer3.select(
        {
          id: `${idPrefix}-pref2`,
          value: form.program_preference_2_code,
          class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        },
        ($$renderer4) => {
          $$renderer4.option({ value: "" }, ($$renderer5) => {
            $$renderer5.push(`None`);
          });
          $$renderer4.push(`<!--[-->`);
          const each_array_2 = ensure_array_like(optionsFor2());
          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
            let program = each_array_2[$$index_2];
            $$renderer4.option({ value: program.code }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
            });
          }
          $$renderer4.push(`<!--]-->`);
        }
      );
      $$renderer3.push(`</div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: `${idPrefix}-pref3`,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Third preference <span class="text-muted-foreground">(optional)</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      $$renderer3.select(
        {
          id: `${idPrefix}-pref3`,
          value: form.program_preference_3_code,
          class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        },
        ($$renderer4) => {
          $$renderer4.option({ value: "" }, ($$renderer5) => {
            $$renderer5.push(`None`);
          });
          $$renderer4.push(`<!--[-->`);
          const each_array_3 = ensure_array_like(optionsFor3());
          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
            let program = each_array_3[$$index_3];
            $$renderer4.option({ value: program.code }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
            });
          }
          $$renderer4.push(`<!--]-->`);
        }
      );
      $$renderer3.push(`</div></div></section> `);
      if (showInternalNotes) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="space-y-1.5">`);
        Label($$renderer3, {
          for: `${idPrefix}-notes`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Internal notes <span class="text-muted-foreground">(registrar)</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Textarea($$renderer3, {
          id: `${idPrefix}-notes`,
          rows: 2,
          get value() {
            return form.correction_notes;
          },
          set value($$value) {
            form.correction_notes = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (showResubmissionNotes) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="space-y-1.5">`);
        Label($$renderer3, {
          for: `${idPrefix}-resubmit-notes`,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->What did you fix? <span class="text-muted-foreground">(optional)</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Textarea($$renderer3, {
          id: `${idPrefix}-resubmit-notes`,
          rows: 3,
          get value() {
            return form.resubmission_notes;
          },
          set value($$value) {
            form.resubmission_notes = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div>`);
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
  IntakeCorrectionFields as default
};
