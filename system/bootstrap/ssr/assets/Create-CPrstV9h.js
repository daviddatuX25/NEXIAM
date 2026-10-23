import { h as head, a as ensure_array_like, e as escape_html, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { a as SvelteDate, b as SvelteSet } from "./watch.svelte-BcPoqckX.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
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
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
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
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./clone-BJz-lxsY.js";
import "./check-DK45Y9SJ.js";
import "./minus-Dqkhcxt7.js";
import "es-toolkit";
import "es-toolkit/compat";
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      academicCycles = [],
      applicants = [],
      activeCycleId = null,
      profiles = [],
      storeRoute = "/exams/direct-assessment"
    } = $$props;
    const now = new SvelteDate();
    const defaultDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    const defaultStartTime = `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;
    const defaultEndDate = new SvelteDate(now.getTime());
    defaultEndDate.setHours(defaultEndDate.getHours() + 1);
    const defaultEndTime = `${String(defaultEndDate.getHours()).padStart(2, "0")}:${String(defaultEndDate.getMinutes()).padStart(2, "0")}`;
    const form = useForm({
      academic_cycle_id: activeCycleId ? String(activeCycleId) : "",
      applicant_ids: [],
      label: "Walk-in Batch 1",
      date: defaultDate,
      start_time: defaultStartTime,
      end_time: defaultEndTime,
      test_instrument_version_id: ""
    });
    let search = "";
    let showSuccessDialog = false;
    const eligibleApplicants = derived(() => {
      const query = search.trim().toLocaleLowerCase();
      if (!query) return applicants;
      return applicants.filter((applicant) => `${applicant.name ?? ""} ${applicant.reference ?? ""}`.toLocaleLowerCase().includes(query));
    });
    const allVisibleSelected = derived(() => eligibleApplicants().length > 0 && eligibleApplicants().every((applicant) => form.applicant_ids.includes(applicant.id)));
    function toggleApplicant(id, checked) {
      const selected = new SvelteSet(form.applicant_ids);
      if (checked) selected.add(id);
      else selected.delete(id);
      form.applicant_ids = [...selected];
    }
    function toggleAllVisible(checked) {
      const selected = new SvelteSet(form.applicant_ids);
      for (const applicant of eligibleApplicants()) {
        if (checked) selected.add(applicant.id);
        else selected.delete(applicant.id);
      }
      form.applicant_ids = [...selected];
    }
    const breadcrumbs = [
      { label: "Exam Scheduling", href: "/exams/sessions" },
      { label: "Direct Assessment" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("uw2c4b", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Create Direct Assessment Session</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Create Direct Assessment Session",
            description: "Create a grading session for walk-in or offline score entry. Bypasses room scheduling and monitoring; creates an attended session ready for scoring."
          });
          $$renderer4.push(`<!----> <form class="space-y-6">`);
          Card($$renderer4, {
            children: ($$renderer5) => {
              Card_header($$renderer5, {
                children: ($$renderer6) => {
                  Card_title($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Session details`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Card_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Choose the academic cycle and assessment time for this
                        cohort.`);
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
                  $$renderer6.push(`<div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2 sm:col-span-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-cycle",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Academic Cycle`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  $$renderer6.select(
                    {
                      id: "direct-assessment-cycle",
                      value: form.academic_cycle_id,
                      required: true,
                      class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                    },
                    ($$renderer7) => {
                      $$renderer7.option({ value: "", disabled: true }, ($$renderer8) => {
                        $$renderer8.push(`Select academic cycle`);
                      });
                      $$renderer7.push(`<!--[-->`);
                      const each_array = ensure_array_like(academicCycles);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let cycle = each_array[$$index];
                        $$renderer7.option({ value: String(cycle.id) }, ($$renderer8) => {
                          $$renderer8.push(`${escape_html(cycle.label)}${escape_html(cycle.is_active ? " (Active)" : "")}`);
                        });
                      }
                      $$renderer7.push(`<!--]-->`);
                    }
                  );
                  $$renderer6.push(` `);
                  if (form.errors.academic_cycle_id) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.academic_cycle_id)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> <div class="space-y-2 sm:col-span-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-label",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Label <span class="text-muted-foreground">(optional)</span>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "direct-assessment-label",
                    placeholder: "e.g. Walk-in Batch 1",
                    get value() {
                      return form.label;
                    },
                    set value($$value) {
                      form.label = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> `);
                  if (form.errors.label) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.label)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-date",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Date`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "direct-assessment-date",
                    type: "date",
                    required: true,
                    get value() {
                      return form.date;
                    },
                    set value($$value) {
                      form.date = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> `);
                  if (form.errors.date) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.date)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> <div class="grid grid-cols-2 gap-3"><div class="space-y-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-start",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Start time`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "direct-assessment-start",
                    type: "time",
                    required: true,
                    get value() {
                      return form.start_time;
                    },
                    set value($$value) {
                      form.start_time = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> `);
                  if (form.errors.start_time) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.start_time)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-end",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->End time`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Input($$renderer6, {
                    id: "direct-assessment-end",
                    type: "time",
                    required: true,
                    get value() {
                      return form.end_time;
                    },
                    set value($$value) {
                      form.end_time = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> `);
                  if (form.errors.end_time) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.end_time)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div></div> <div class="space-y-2 sm:col-span-2">`);
                  Label($$renderer6, {
                    for: "direct-assessment-profile",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Test Instrument Profile <span class="text-muted-foreground">(optional)</span>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  $$renderer6.select(
                    {
                      id: "direct-assessment-profile",
                      value: form.test_instrument_version_id,
                      class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                    },
                    ($$renderer7) => {
                      $$renderer7.option({ value: "" }, ($$renderer8) => {
                        $$renderer8.push(`No instrument profile (traditional scoring)`);
                      });
                      $$renderer7.push(`<!--[-->`);
                      const each_array_1 = ensure_array_like(profiles);
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let profile = each_array_1[$$index_1];
                        $$renderer7.option({ value: String(profile.id) }, ($$renderer8) => {
                          $$renderer8.push(`${escape_html(profile.display_name)} (${escape_html(profile.instrument_code)}
                                        v${escape_html(profile.version_number)})`);
                        });
                      }
                      $$renderer7.push(`<!--]-->`);
                    }
                  );
                  $$renderer6.push(` `);
                  if (form.errors.test_instrument_version_id) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.test_instrument_version_id)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Card($$renderer4, {
            children: ($$renderer5) => {
              Card_header($$renderer5, {
                children: ($$renderer6) => {
                  $$renderer6.push(`<div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div>`);
                  Card_title($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Eligible Applicants`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Card_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Select the accepted applicants to register for
                                direct assessment.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----></div> `);
                  Badge($$renderer6, {
                    variant: "outline",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->${escape_html(form.applicant_ids.length)} selected`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Card_content($$renderer5, {
                children: ($$renderer6) => {
                  $$renderer6.push(`<div class="space-y-4">`);
                  Input($$renderer6, {
                    type: "search",
                    placeholder: "Search name or reference…",
                    "aria-label": "Search eligible applicants by name or reference",
                    get value() {
                      return search;
                    },
                    set value($$value) {
                      search = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer6.push(`<!----> <label class="flex min-h-10 items-center gap-3 rounded-md border px-3 text-sm font-medium">`);
                  Checkbox($$renderer6, {
                    checked: allVisibleSelected(),
                    onCheckedChange: (checked) => toggleAllVisible(Boolean(checked))
                  });
                  $$renderer6.push(`<!----> Select All</label> <div class="max-h-112 divide-y overflow-y-auto rounded-md border">`);
                  if (eligibleApplicants().length === 0) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-muted-foreground p-8 text-center text-sm">No eligible applicants found.</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                    $$renderer6.push(`<!--[-->`);
                    const each_array_2 = ensure_array_like(eligibleApplicants());
                    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                      let applicant = each_array_2[$$index_2];
                      $$renderer6.push(`<label class="hover:bg-muted/40 flex cursor-pointer items-start gap-3 p-3">`);
                      Checkbox($$renderer6, {
                        checked: form.applicant_ids.includes(applicant.id),
                        onCheckedChange: (checked) => toggleApplicant(applicant.id, Boolean(checked))
                      });
                      $$renderer6.push(`<!----> <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">${escape_html(applicant.name)}</span> <span class="text-muted-foreground mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs"><span class="font-mono">${escape_html(applicant.reference)}</span> <span>${escape_html(applicant.course_preference ?? "No course preference")}</span></span></span></label>`);
                    }
                    $$renderer6.push(`<!--]-->`);
                  }
                  $$renderer6.push(`<!--]--></div> `);
                  if (form.errors.applicant_ids) {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`<p class="text-destructive text-sm">${escape_html(form.errors.applicant_ids)}</p>`);
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]--></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">`);
          Link($$renderer4, {
            href: "/exams/sessions",
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                class: "min-h-10",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10",
            disabled: form.applicant_ids.length === 0 || form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Creating…" : `Create Session (${form.applicant_ids.length})`)}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return showSuccessDialog;
              },
              set open($$value) {
                showSuccessDialog = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-lg",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Direct Assessment Created`);
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
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->The applicants have been registered for direct assessment
                    and are ready for score encoding. Please contact a Test
                    Administrator to begin grading.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              onclick: () => showSuccessDialog = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Stay Here`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Link($$renderer7, {
                              href: "/exams/sessions",
                              children: ($$renderer8) => {
                                Button($$renderer8, {
                                  children: ($$renderer9) => {
                                    $$renderer9.push(`<!---->Go to Exam Scheduling`);
                                  },
                                  $$slots: { default: true }
                                });
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
