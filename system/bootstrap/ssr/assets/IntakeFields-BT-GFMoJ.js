import { h as head, a as ensure_array_like, e as escape_html, b as attr, B as Button } from "../app.js";
import { S as StaffLayout, C as Calendar } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { S as Sliders_horizontal } from "./PreferencesMenu-CCPeta-5.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
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
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
function IntakeFields($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      cycles = [],
      selected_cycle_id = null,
      allow_guidance_all_in_one_intake = false,
      policy_version = 1,
      grouped_policies = {},
      requirement_levels = [],
      capabilities = {}
    } = $$props;
    let policyItems = [];
    let saving = false;
    function handleLevelChange(fieldKey, newLevel) {
      const idx = policyItems.findIndex((p) => p.field_key === fieldKey);
      if (idx !== -1 && !policyItems[idx].is_mandatory_floor) {
        policyItems[idx].requirement_level = newLevel;
      }
    }
    function onCycleChange(cycleId) {
      router.get("/setup/intake-fields", { cycle_id: cycleId });
    }
    const groupLabels = {
      identity: "Core Identity & Demographics",
      contact: "Contact & Location Details",
      academic: "Prior Academic History & Origin",
      program_choices: "Degree Program Choices & Alternatives"
    };
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Intake field requirements" }
    ];
    head("f6k99s", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Intake field requirements · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            $$renderer4.push(`<div class="flex items-center gap-2">`);
            Badge($$renderer4, {
              variant: "outline",
              class: "font-mono text-xs",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Revision v${escape_html(policy_version)}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Link($$renderer4, {
              href: "/setup",
              children: ($$renderer5) => {
                Button($$renderer5, {
                  variant: "outline",
                  size: "sm",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Back to readiness`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div>`);
          };
          PageHeaderBar($$renderer3, {
            title: "Intake & Import Field Policy",
            description: "Configure institutional requirement levels for applicant intake forms and spreadsheet imports. Core identity fields are protected by a code-owned non-negotiable floor.",
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-border bg-card p-4"><div class="flex items-center gap-3"><span class="inline-flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
        Calendar($$renderer3, { class: "size-4" });
        $$renderer3.push(`<!----></span> <div><p class="text-xs font-bold text-foreground">Governing Academic Cycle</p> <p class="text-[11px] text-muted-foreground">Requirements are versioned per cycle to prevent invalidating historic applications.</p></div></div> <div class="flex items-center gap-2">`);
        $$renderer3.select(
          {
            id: "cycle-picker",
            value: selected_cycle_id,
            onchange: (e) => onCycleChange(e.target.value),
            class: "flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          },
          ($$renderer4) => {
            $$renderer4.push(`<!--[-->`);
            const each_array = ensure_array_like(cycles);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let cycle = each_array[$$index];
              $$renderer4.option({ value: cycle.id }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(cycle.label)} ${escape_html(cycle.is_active ? "★ (Active)" : "")}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</div></div> <div class="rounded-xl border border-primary/20 bg-primary/5 p-4"><div class="flex items-start gap-3">`);
        Shield_check($$renderer3, { class: "mt-0.5 size-5 shrink-0 text-primary" });
        $$renderer3.push(`<!----> <div class="space-y-1 text-xs"><p class="font-bold text-foreground">Non-Negotiable System Floor (D-085 &amp; PostgreSQL Constraints)</p> <p class="text-muted-foreground leading-relaxed"><strong class="text-foreground">First Name</strong>, <strong class="text-foreground">Last Name</strong>, <strong class="text-foreground">Date of Birth</strong>, <strong class="text-foreground">Sex</strong>, and <strong class="text-foreground">1st Choice Program</strong> are permanently system-mandatory. Birthdate is required for applicant portal access-key authentication (D-085); Sex and Names are required for deduplicated identity keys and cannot be disabled.</p></div></div></div> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "border-border bg-card shadow-sm",
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        class: "text-sm",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Interoffice Intake Delegation`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Card_description) {
                      $$renderer5.push("<!--[-->");
                      Card_description($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Allow Guidance Counselors to stage and commit All-in-One examinee files directly without requiring separate Registrar approval.`);
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
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  children: ($$renderer5) => {
                    $$renderer5.push(`<label class="flex cursor-pointer items-center gap-3 text-sm text-foreground"><input type="checkbox"${attr("checked", allow_guidance_all_in_one_intake, true)}${attr("disabled", !selected_cycle_id, true)} class="size-4 rounded border-border accent-primary"/> <span>Allow Guidance All-in-One intake delegation for this academic cycle</span></label>`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` <form class="space-y-6"><!--[-->`);
        const each_array_1 = ensure_array_like(Object.keys(groupLabels));
        for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
          let groupKey = each_array_1[$$index_2];
          const fieldsInGroup = policyItems.filter((p) => p.field_group === groupKey);
          if (fieldsInGroup.length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden"><div class="border-b border-border/60 bg-muted/40 px-5 py-3 flex items-center justify-between"><h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">`);
            Sliders_horizontal($$renderer3, { class: "size-3.5 text-primary" });
            $$renderer3.push(`<!----> ${escape_html(groupLabels[groupKey] ?? groupKey)}</h2> <span class="text-[11px] text-muted-foreground font-mono">${escape_html(fieldsInGroup.length)} field${escape_html(fieldsInGroup.length === 1 ? "" : "s")}</span></div> <div class="divide-y divide-border/60"><!--[-->`);
            const each_array_2 = ensure_array_like(fieldsInGroup);
            for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
              let field = each_array_2[$$index_1];
              $$renderer3.push(`<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 hover:bg-muted/20 transition-colors"><div class="space-y-0.5"><div class="flex items-center gap-2"><span class="text-xs font-bold text-foreground">${escape_html(field.label)}</span> `);
              if (field.is_mandatory_floor) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-bold bg-primary/10 text-primary border border-primary/20">`);
                Lock($$renderer3, { class: "size-3" });
                $$renderer3.push(`<!----> Locked Floor</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<code class="text-[10px] text-muted-foreground font-mono">${escape_html(field.field_key)}</code>`);
              }
              $$renderer3.push(`<!--]--></div> <p class="text-[11px] text-muted-foreground">`);
              if (field.is_mandatory_floor) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`Protected schema minimum; verified by both portal and spreadsheet import.`);
              } else if (field.requirement_level === "institution_required") {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`Strictly required by this institution; rows missing this value fail validation.`);
              } else if (field.requirement_level === "optional") {
                $$renderer3.push("<!--[2-->");
                $$renderer3.push(`Accepted if present; records pass validation if omitted.`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`Disabled for this academic cycle; safely skipped during intake.`);
              }
              $$renderer3.push(`<!--]--></p></div> <div class="flex items-center gap-2 shrink-0">`);
              if (field.is_mandatory_floor) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="text-xs font-semibold font-mono text-primary px-3 py-1.5 rounded-lg bg-primary/10 border border-primary/20">System Mandatory</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.select(
                  {
                    value: field.requirement_level,
                    onchange: (e) => handleLevelChange(field.field_key, e.target.value),
                    class: "flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  },
                  ($$renderer4) => {
                    $$renderer4.option({ value: "institution_required" }, ($$renderer5) => {
                      $$renderer5.push(`Required`);
                    });
                    $$renderer4.option({ value: "optional" }, ($$renderer5) => {
                      $$renderer5.push(`Optional`);
                    });
                    $$renderer4.option({ value: "disabled" }, ($$renderer5) => {
                      $$renderer5.push(`Disabled`);
                    });
                  }
                );
              }
              $$renderer3.push(`<!--]--></div></div>`);
            }
            $$renderer3.push(`<!--]--></div></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--> <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-sm"><div class="text-xs text-muted-foreground">Saving creates a <strong class="text-foreground">new policy revision (v${escape_html(policy_version + 1)})</strong> for this academic cycle. <span class="block text-[11px] text-muted-foreground/80">Already-accepted applicants remain protected under their historical submission revision.</span></div> <div class="flex items-center gap-2 shrink-0">`);
        Button($$renderer3, {
          type: "submit",
          disabled: saving,
          class: "min-h-10 gap-2 font-bold shadow-sm",
          children: ($$renderer4) => {
            Save($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!----> ${escape_html(`Save Revision v${policy_version + 1}`)}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div></form></div>`);
      }
    });
  });
}
export {
  IntakeFields as default
};
