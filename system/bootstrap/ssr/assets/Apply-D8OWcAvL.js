import { h as head, e as escape_html, a as ensure_array_like, b as attr, B as Button, d as derived } from "../app.js";
import { H as HomeLayout } from "./HomeLayout-Cd36uhjl.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { P as PhilippineAddressSelector } from "./PhilippineAddressSelector-UGmHumOi.js";
import { I as InfoPopover } from "./InfoPopover-CdH5YJnv.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
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
import "./Link-B7ks7rBK.js";
import "./circle-question-mark-DY-RU614.js";
/* empty css                                                        */
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Apply($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      institution = null,
      cycle = null,
      window = null,
      programs = [],
      grade_fields = [],
      privacy_policy = null
    } = $$props;
    const initialGrades = Object.fromEntries((grade_fields ?? []).map((f) => [f.code, ""]));
    const form = useForm({
      academic_cycle_id: cycle?.id ?? "",
      application_window_id: window?.id ?? "",
      privacy_policy_id: privacy_policy?.id ?? "",
      privacy_policy_revision: privacy_policy?.revision ?? "",
      first_name: "",
      middle_name: "",
      last_name: "",
      suffix: "",
      email: "",
      birthdate: "",
      sex: "",
      applicant_type: "new",
      last_school_enrolled: "",
      strand: "",
      grades: initialGrades,
      phone: "",
      address_line: "",
      city: "",
      province: "",
      zip_code: "",
      program_preference_1: "",
      program_preference_2: "",
      program_preference_3: "",
      terms_accepted: false
    });
    let policyDialogOpen = false;
    let tourOpen = false;
    let subjectsOpen = false;
    const policySections = derived(() => {
      const raw = privacy_policy?.content ?? "";
      if (!raw) return [];
      const parts = raw.split(/\n\n(?=\d+\.\s+[A-Z\s&]+)/);
      return parts.map((part) => {
        const match = part.match(/^(\d+\.\s+[A-Z\s&]+)\n\n([\s\S]+)$/);
        if (match) {
          return { heading: match[1], body: match[2].trim() };
        }
        return { heading: null, body: part.trim() };
      });
    });
    const gwaFields = derived(() => (grade_fields ?? []).filter((f) => f.field_kind === "gwa" || f.code === "GWA"));
    const subjectFields = derived(() => (grade_fields ?? []).filter((f) => f.field_kind !== "gwa" && f.code !== "GWA"));
    const tourContext = derived(() => ({
      hasCycle: Boolean(cycle),
      hasWindow: Boolean(window),
      programCount: programs.length,
      hasGradeFields: grade_fields.length > 0
    }));
    const optionsFor2 = derived(() => programs.filter((p) => p.code !== form.program_preference_1));
    const optionsFor3 = derived(() => programs.filter((p) => p.code !== form.program_preference_1 && p.code !== form.program_preference_2));
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("zyy8nn", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Admission application</title>`);
        });
      });
      HomeLayout($$renderer3, {
        institution,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 py-8 sm:py-10"><header class="space-y-2 border-b border-border/60 pb-5" data-tour="apply-header"><div class="flex items-center justify-between gap-3"><div><p class="text-sm text-muted-foreground">${escape_html(institution?.name ?? "Institution")}</p> <h1 class="text-3xl font-semibold tracking-tight">Admission application</h1></div> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "applicant-apply-guide",
            label: "Need help with your application? Ask Kuya Xiam!",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----></div> `);
          if (window) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-muted-foreground">${escape_html(window.name)} `);
            if (cycle) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`· ${escape_html(cycle.name)}`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></header> `);
          if (page_state === "empty" || !window) {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "pt-6 text-sm text-muted-foreground",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->No admission period is currently open. Please contact the Registrar’s office for details.`);
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
          } else {
            $$renderer4.push("<!--[-1-->");
            if (form.errors.identity || form.errors.privacy_policy_id || form.errors.privacy_policy_revision || form.errors.application_window_id || form.errors.academic_cycle_id) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">${escape_html(form.errors.identity ?? form.errors.privacy_policy_id ?? form.errors.privacy_policy_revision ?? form.errors.application_window_id ?? form.errors.academic_cycle_id)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <form class="space-y-8" novalidate=""><section class="space-y-4" data-tour="apply-personal-info"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold tracking-tight text-foreground">Personal information</h2> <span class="text-xs text-muted-foreground">Official PSA records</span></div> <div class="rounded-xl border border-border/70 bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Enter your legal name, birthdate, and demographic information exactly as shown on your PSA birth certificate or official school documents for identity verification.</div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2">`);
            Label($$renderer4, {
              for: "first_name",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->First name <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "first_name",
              required: true,
              class: "min-h-11",
              get value() {
                return form.first_name;
              },
              set value($$value) {
                form.first_name = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (form.errors.first_name) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.first_name)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "last_name",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Last name <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "last_name",
              required: true,
              class: "min-h-11",
              get value() {
                return form.last_name;
              },
              set value($$value) {
                form.last_name = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (form.errors.last_name) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.last_name)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "middle_name",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Middle name <span class="text-muted-foreground font-normal">(optional)</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "middle_name",
              class: "min-h-11",
              get value() {
                return form.middle_name;
              },
              set value($$value) {
                form.middle_name = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "suffix",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Suffix <span class="text-muted-foreground font-normal">(optional)</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "suffix",
              class: "min-h-11",
              placeholder: "Jr., III",
              get value() {
                return form.suffix;
              },
              set value($$value) {
                form.suffix = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "email",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Email <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "email",
              type: "email",
              required: true,
              class: "min-h-11",
              get value() {
                return form.email;
              },
              set value($$value) {
                form.email = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (form.errors.email) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.email)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "birthdate",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Birthdate <span class="text-destructive">*</span>`);
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
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "sex",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Sex <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "sex",
                value: form.sex,
                required: true,
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "", disabled: true }, ($$renderer6) => {
                  $$renderer6.push(`Select`);
                });
                $$renderer5.option({ value: "female" }, ($$renderer6) => {
                  $$renderer6.push(`Female`);
                });
                $$renderer5.option({ value: "male" }, ($$renderer6) => {
                  $$renderer6.push(`Male`);
                });
              }
            );
            $$renderer4.push(` `);
            if (form.errors.sex) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.sex)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2"><div class="flex items-center gap-1.5">`);
            Label($$renderer4, {
              for: "applicant_type",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Applicant classification <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            InfoPopover($$renderer4, {
              content: "Select 'New' if you are a graduating or graduated Senior High student entering college for the first time, or 'Transferee' if you previously earned college units elsewhere."
            });
            $$renderer4.push(`<!----></div> `);
            $$renderer4.select(
              {
                id: "applicant_type",
                value: form.applicant_type,
                required: true,
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "new" }, ($$renderer6) => {
                  $$renderer6.push(`New`);
                });
                $$renderer5.option({ value: "transferee" }, ($$renderer6) => {
                  $$renderer6.push(`Transferee`);
                });
              }
            );
            $$renderer4.push(`</div></div></section> <section class="space-y-4" data-tour="apply-academic-background"><h2 class="text-lg font-semibold tracking-tight text-foreground">Academic background</h2> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2 sm:col-span-2">`);
            Label($$renderer4, {
              for: "last_school_enrolled",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Last school enrolled`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "last_school_enrolled",
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
            $$renderer4.push(`<!----></div> <div class="space-y-2"><div class="flex items-center gap-1.5">`);
            Label($$renderer4, {
              for: "strand",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(form.applicant_type === "transferee" ? "Previous course" : "SHS strand")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            InfoPopover($$renderer4, {
              content: "Your Senior High School track or strand (e.g. STEM, ABM, HUMSS, TVL), or previous collegiate course name."
            });
            $$renderer4.push(`<!----></div> `);
            Input($$renderer4, {
              id: "strand",
              class: "min-h-11",
              placeholder: "e.g. STEM",
              get value() {
                return form.strand;
              },
              set value($$value) {
                form.strand = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> <!--[-->`);
            const each_array = ensure_array_like(gwaFields());
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let field = each_array[$$index];
              $$renderer4.push(`<div class="space-y-2">`);
              Label($$renderer4, {
                for: `grade_${field.code}`,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(field.label)} <span class="text-destructive">*</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                id: `grade_${field.code}`,
                type: "number",
                step: "0.01",
                min: field.min_value,
                max: field.max_value,
                required: field.is_required,
                class: "min-h-11 font-mono",
                placeholder: "e.g. 88.50",
                get value() {
                  return form.grades[field.code];
                },
                set value($$value) {
                  form.grades[field.code] = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              if (form.errors[`grades.${field.code}`]) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors[`grades.${field.code}`])}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            }
            $$renderer4.push(`<!--]--> `);
            if (subjectFields().length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="sm:col-span-2 pt-1"><details class="group rounded-xl border border-border/80 bg-card/40 p-4 transition-all"${attr("open", subjectsOpen, true)}><summary class="flex cursor-pointer items-center justify-between text-sm font-medium text-foreground select-none"><span class="flex items-center gap-2"><span>Additional subject grades</span> <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground font-normal">${escape_html(subjectFields().some((f) => f.is_required) ? "Catalog fields" : "Optional")}</span></span> `);
              Chevron_down($$renderer4, {
                class: "size-4 text-muted-foreground transition-transform duration-200 group-open:rotate-180"
              });
              $$renderer4.push(`<!----></summary> <div class="mt-3 space-y-4 border-t border-border/60 pt-3"><p class="text-xs text-muted-foreground leading-relaxed">Subject grades (Math, Science, English, etc.) are optional and used strictly for Academic Guidance course counseling. You may leave them blank. The counselor can also record them during consultation, or you can update them later while your application is pending review.</p> <div class="grid gap-4 sm:grid-cols-2"><!--[-->`);
              const each_array_1 = ensure_array_like(subjectFields());
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let field = each_array_1[$$index_1];
                $$renderer4.push(`<div class="space-y-2">`);
                Label($$renderer4, {
                  for: `grade_${field.code}`,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(field.label)}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Input($$renderer4, {
                  id: `grade_${field.code}`,
                  type: "number",
                  step: "0.01",
                  min: field.min_value,
                  max: field.max_value,
                  placeholder: field.is_required ? "Required" : "Optional",
                  class: "min-h-11 font-mono",
                  get value() {
                    return form.grades[field.code];
                  },
                  set value($$value) {
                    form.grades[field.code] = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----> `);
                if (form.errors[`grades.${field.code}`]) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors[`grades.${field.code}`])}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              }
              $$renderer4.push(`<!--]--></div></div></details></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div></section> <section class="space-y-4"><div class="flex items-center justify-between"><h2 class="text-lg font-medium">Contact information</h2> <span class="text-xs text-muted-foreground">Optional</span></div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2 sm:col-span-2">`);
            Label($$renderer4, {
              for: "phone",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Phone <span class="text-muted-foreground font-normal">(optional)</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "phone",
              type: "tel",
              class: "min-h-11",
              placeholder: "e.g. 09171234567",
              get value() {
                return form.phone;
              },
              set value($$value) {
                form.phone = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div></div> `);
            PhilippineAddressSelector($$renderer4, {
              idPrefix: "apply-addr",
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
            $$renderer4.push(`<!----></section> <section class="space-y-4" data-tour="apply-program-preferences"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold tracking-tight text-foreground">Program preferences</h2> <span class="text-xs text-muted-foreground">Degree choices</span></div> <div class="rounded-xl border border-border/70 bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Choose up to three programs in order of preference. Your 1st preference is your primary admission goal; 2nd and 3rd choices serve as alternative degree options if first-choice quotas are reached.</div> <div class="space-y-3"><div class="space-y-2"><div class="flex items-center gap-1.5">`);
            Label($$renderer4, {
              for: "program_preference_1",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->First preference <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            InfoPopover($$renderer4, {
              content: "Your primary intended degree program. This is evaluated first for ranking and direct qualification."
            });
            $$renderer4.push(`<!----></div> `);
            $$renderer4.select(
              {
                id: "program_preference_1",
                value: form.program_preference_1,
                required: true,
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "", disabled: true }, ($$renderer6) => {
                  $$renderer6.push(`Select a program`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_2 = ensure_array_like(programs);
                for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                  let program = each_array_2[$$index_2];
                  $$renderer5.option({ value: program.code }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(` `);
            if (form.errors.program_preference_1) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.program_preference_1)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "program_preference_2",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Second preference <span class="text-muted-foreground font-normal">(optional)</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "program_preference_2",
                value: form.program_preference_2,
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`None`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_3 = ensure_array_like(optionsFor2());
                for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                  let program = each_array_3[$$index_3];
                  $$renderer5.option({ value: program.code }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</div> <div class="space-y-2">`);
            Label($$renderer4, {
              for: "program_preference_3",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Third preference <span class="text-muted-foreground font-normal">(optional)</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "program_preference_3",
                value: form.program_preference_3,
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`None`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_4 = ensure_array_like(optionsFor3());
                for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                  let program = each_array_4[$$index_4];
                  $$renderer5.option({ value: program.code }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(program.name)} (${escape_html(program.code)})`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</div></div></section> <section class="space-y-3 rounded-md border bg-muted/30 px-4 py-4"><label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox"${attr("checked", form.terms_accepted, true)} class="mt-1" required=""/> <span>I certify that the information provided is true and complete, and I have read and agree to the <button type="button" class="font-medium text-foreground underline underline-offset-4">Privacy Policy</button> (including digital academic grades for consultation).</span></label> `);
            if (form.errors.terms_accepted) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.terms_accepted)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section> `);
            Button($$renderer4, {
              type: "submit",
              class: "min-h-11 w-full sm:w-auto",
              disabled: form.processing,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(form.processing ? "Submitting…" : "Submit application")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></form>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return policyDialogOpen;
              },
              set open($$value) {
                policyDialogOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "fixed inset-x-0 bottom-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 rounded-t-2xl sm:rounded-2xl max-h-[90vh] sm:max-w-2xl md:max-w-3xl flex flex-col p-0 overflow-hidden shadow-2xl border border-border/80 bg-card",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          class: "p-5 border-b border-border/60 bg-muted/30 shrink-0",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<div class="flex items-center gap-2.5"><div class="size-8 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">`);
                            Shield_check($$renderer7, { class: "size-4.5" });
                            $$renderer7.push(`<!----></div> <div>`);
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                class: "text-base sm:text-lg font-bold text-foreground",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(privacy_policy?.title ?? "Institutional Privacy Policy")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                class: "text-xs text-muted-foreground mt-0.5",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Data Privacy Act of 2012 (RA 10173) • Verified Institutional Notice`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(`</div></div>`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4 text-sm leading-relaxed"><div class="rounded-xl border border-primary/20 bg-primary/5 p-4 flex items-start gap-3">`);
                      Lock($$renderer6, { class: "size-4 text-primary shrink-0 mt-0.5" });
                      $$renderer6.push(`<!----> <div class="text-xs text-muted-foreground leading-relaxed"><p class="font-semibold text-foreground">Compliance &amp; Credential Notice</p> <p class="mt-0.5">Please review this statutory policy carefully before accepting. Digital academic grades entered online are strictly for admissions suitability assessment; physical Form 138 / TOR verification remains required at the Registrar.</p></div></div> `);
                      if (policySections().length > 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<!--[-->`);
                        const each_array_5 = ensure_array_like(policySections());
                        for (let $$index_6 = 0, $$length = each_array_5.length; $$index_6 < $$length; $$index_6++) {
                          let sec = each_array_5[$$index_6];
                          if (sec.heading) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="rounded-xl border border-border/60 bg-muted/20 p-4 space-y-2.5"><h4 class="font-mono text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-2"><span class="size-1.5 rounded-full bg-primary shrink-0"></span> ${escape_html(sec.heading)}</h4> <div class="text-xs text-foreground/90 space-y-1.5 leading-relaxed"><!--[-->`);
                            const each_array_6 = ensure_array_like(sec.body.split("\n"));
                            for (let $$index_5 = 0, $$length2 = each_array_6.length; $$index_5 < $$length2; $$index_5++) {
                              let line = each_array_6[$$index_5];
                              if (line.trim().startsWith("•")) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`<div class="flex items-start gap-2 pl-2"><span class="size-1.5 rounded-full bg-muted-foreground/60 shrink-0 mt-1.5"></span> <span>${escape_html(line.trim().replace(/^•\s*/, ""))}</span></div>`);
                              } else if (line.trim()) {
                                $$renderer6.push("<!--[1-->");
                                $$renderer6.push(`<p>${escape_html(line.trim())}</p>`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]-->`);
                            }
                            $$renderer6.push(`<!--]--></div></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<div class="p-3 text-xs text-muted-foreground leading-relaxed border-b border-border/40 pb-3"><p class="whitespace-pre-wrap">${escape_html(sec.body)}</p></div>`);
                          }
                          $$renderer6.push(`<!--]-->`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<div class="p-4 text-center text-xs text-muted-foreground">No privacy policy has been published yet. Contact the Registrar’s office before applying.</div>`);
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          class: "m-0 border-t border-border/60 bg-muted/40 shrink-0 flex flex-col-reverse sm:flex-row items-center justify-between gap-3 w-full p-4 sm:p-5 pb-5 sm:pb-5 sm:rounded-b-2xl",
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-11 sm:min-h-10 w-full px-4 sm:w-auto",
                              onclick: () => policyDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Close &amp; Return`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "default",
                              class: "min-h-11 sm:min-h-10 w-full px-4 sm:w-auto gap-2",
                              onclick: () => {
                                form.terms_accepted = true;
                                policyDialogOpen = false;
                              },
                              children: ($$renderer8) => {
                                Circle_check($$renderer8, { class: "size-4" });
                                $$renderer8.push(`<!----> I Agree &amp; Accept Policy`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!---->`);
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
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-apply-guide",
            context: tourContext(),
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
  Apply as default
};
