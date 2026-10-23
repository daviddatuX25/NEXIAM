import { B as Button, e as escape_html, a as ensure_array_like, d as derived, n as newIdempotencyKey, b as attr, c as attr_class, h as head, f as attr_style, s as stringify, u as usePage } from "../app.js";
import { g as governanceRequest, a as governanceRoute, b as governanceError } from "./governanceRequests-C1KnF59a.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Table_caption } from "./table-caption-Cs_SOvQm.js";
import { T as Tabs, a as Tabs_list, b as Tabs_trigger, c as Tabs_content } from "./tabs-trigger-DxnTaeSp.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import "clsx";
import { S as Sliders_horizontal } from "./PreferencesMenu-CCPeta-5.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { X } from "./x-CnxrMbMn.js";
import { T as TabFmResultView } from "./TabFmResultView-ykz3bqxf.js";
import { R as Radar } from "./radar-CMV2oDsL.js";
import { C as ConsultationMobileDock, A as ApplicantLookupDialog } from "./ApplicantLookupDialog-DWMtphZu.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { B as Building_2 } from "./building-2-BaeEMGXC.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./Link-B7ks7rBK.js";
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
import "./shield-alert-CXtDdte9.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./noop-BQS7OFs8.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./scroll-lock-B1ibcKMx.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./arrow-left-DbWnQR3t.js";
function ApplicantEvidenceBar($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const PARENT_LABELS = {
      riasec_r: "Realistic",
      riasec_i: "Investigative",
      riasec_a: "Artistic",
      riasec_s: "Social",
      riasec_e: "Enterprising",
      riasec_c: "Conventional"
    };
    let {
      evidence = {},
      inputSchema = null,
      onRecordSupplementalEvidence = () => {
      }
    } = $$props;
    const gwa = derived(() => evidence?.gwa === null || evidence?.gwa === void 0 ? null : evidence.gwa);
    const strand = derived(() => evidence?.strand || null);
    const subjectGrades = derived(() => evidence?.subject_grades && typeof evidence.subject_grades === "object" ? evidence.subject_grades : {});
    const subjectDefinitions = derived(() => new Map((Array.isArray(inputSchema?.subjects) ? inputSchema.subjects : []).map((subject) => [subject.key, subject])));
    const recordedSubjects = derived(() => Object.entries(subjectGrades()).map(([key, value]) => ({
      key,
      value,
      label: subjectDefinitions().get(key)?.label ?? evidence?.subject_grade_labels?.[key] ?? key,
      unit: subjectDefinitions().get(key)?.unit ?? evidence?.subject_grade_units?.[key] ?? null
    })));
    const activityDefinitions = derived(() => new Map((Array.isArray(inputSchema?.activities) ? inputSchema.activities : []).map((activity) => [String(activity.id), activity])));
    const activityIds = derived(() => Array.isArray(evidence?.vocational_activities) ? evidence.vocational_activities : Array.isArray(evidence?.vocational_tags) ? evidence.vocational_tags : []);
    const activityGroups = derived(() => Object.entries(PARENT_LABELS).map(([parent, label]) => ({
      parent,
      label,
      activities: activityIds().map((id) => activityDefinitions().get(String(id)) ?? { id, label: String(id), parent: null }).filter((activity) => activity.parent === parent)
    })).filter((group) => group.activities.length > 0));
    const ungroupedActivities = derived(() => activityIds().map((id) => activityDefinitions().get(String(id)) ?? { id, label: String(id), parent: null }).filter((activity) => !PARENT_LABELS[activity.parent]));
    const riasecTags = derived(() => Array.isArray(evidence?.riasec_tags) ? evidence.riasec_tags : []);
    const hasSupplementalEvidence = derived(() => Boolean(evidence?.evidence_source || Object.keys(subjectGrades()).length || activityIds().length || riasecTags().length || evidence?.activities_reviewed === true || evidence?.counselor_notes));
    const evidenceSourceLabel = derived(() => evidence?.evidence_source_label ?? ({
      physical_form_138: "Reviewed physical Form 138 / Report Card",
      examinee_statement: "Applicant declaration"
    }[evidence?.evidence_source] ?? evidence?.evidence_source ?? null));
    $$renderer2.push(`<div class="rounded-lg border border-border/60 bg-muted/20 p-3 shadow-xs transition-all duration-200"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/40 pb-2.5"><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Applicant Evidence</span> `);
    if (hasSupplementalEvidence()) {
      $$renderer2.push("<!--[0-->");
      Badge($$renderer2, {
        variant: "outline",
        class: "gap-1 border-emerald-600/40 bg-emerald-600/10 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Supplemental evidence recorded`);
        },
        $$slots: { default: true }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-[11px] text-muted-foreground">· Sealed Intake &amp; Assessment</span>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    Button($$renderer2, {
      type: "button",
      variant: "outline",
      size: "sm",
      class: "h-7 gap-1.5 px-2.5 text-xs font-semibold shadow-2xs",
      onclick: onRecordSupplementalEvidence,
      children: ($$renderer3) => {
        Sliders_horizontal($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----> + Record Supplemental Evidence`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> `);
    if (evidenceSourceLabel()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="mt-2 text-[11px] text-muted-foreground">Source: <span class="font-medium text-foreground">${escape_html(evidenceSourceLabel())}</span></p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="mt-2.5 grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-2 lg:grid-cols-4"><div class="space-y-1 rounded-md border border-border/40 bg-background/60 p-2"><div class="text-[10px] font-semibold uppercase text-muted-foreground">High School GWA</div> <div class="flex items-center gap-2">`);
    if (gwa() !== null) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="font-mono text-sm font-bold text-foreground">${escape_html(gwa())}</span> `);
      if (evidence.gwa_band) {
        $$renderer2.push("<!--[0-->");
        Badge($$renderer2, {
          variant: "secondary",
          class: "text-[10px] font-medium",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(evidence.gwa_band)}`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-[11px] italic text-muted-foreground">Unreported</span>`);
    }
    $$renderer2.push(`<!--]--></div></div> <div class="space-y-1 rounded-md border border-border/40 bg-background/60 p-2"><div class="text-[10px] font-semibold uppercase text-muted-foreground">SHS Track &amp; Strand</div> <div class="flex items-center gap-1.5">`);
    if (strand()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="font-semibold text-foreground">${escape_html(strand())}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-[11px] italic text-muted-foreground">Unreported</span>`);
    }
    $$renderer2.push(`<!--]--></div></div> <div class="space-y-1 rounded-md border border-border/40 bg-background/60 p-2"><div class="text-[10px] font-semibold uppercase text-muted-foreground">Recorded subject fields</div> `);
    if (recordedSubjects().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]"><!--[-->`);
      const each_array = ensure_array_like(recordedSubjects());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let subject = each_array[$$index];
        $$renderer2.push(`<span class="rounded bg-muted px-1.5 py-0.5 font-semibold text-foreground">${escape_html(subject.label)}: ${escape_html(subject.value === null || subject.value === void 0 ? "Missing" : `${subject.value}${subject.unit ? ` ${subject.unit}` : ""}`)}</span>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-[11px] italic text-muted-foreground">No subject values recorded</span>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="space-y-1 rounded-md border border-border/40 bg-background/60 p-2"><div class="text-[10px] font-semibold uppercase text-muted-foreground">Vocational activities</div> `);
    if (evidence.activities_reviewed === true) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-[10px] text-muted-foreground">Activities reviewed</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="text-[10px] italic text-muted-foreground">Activity review not recorded</p>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (activityGroups().length > 0 || riasecTags().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="space-y-1"><!--[-->`);
      const each_array_1 = ensure_array_like(activityGroups());
      for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
        let group = each_array_1[$$index_2];
        $$renderer2.push(`<div class="flex flex-wrap items-center gap-1"><span class="text-[10px] font-medium text-muted-foreground">${escape_html(group.label)}</span> <!--[-->`);
        const each_array_2 = ensure_array_like(group.activities);
        for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
          let activity = each_array_2[$$index_1];
          Badge($$renderer2, {
            variant: "secondary",
            class: "bg-primary/10 text-[10px] font-medium text-primary",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->${escape_html(activity.label)}`);
            },
            $$slots: { default: true }
          });
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--> <!--[-->`);
      const each_array_3 = ensure_array_like(ungroupedActivities());
      for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
        let activity = each_array_3[$$index_3];
        Badge($$renderer2, {
          variant: "outline",
          class: "font-mono text-[10px]",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(activity.id)}`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]--> <!--[-->`);
      const each_array_4 = ensure_array_like(riasecTags());
      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
        let tag = each_array_4[$$index_4];
        Badge($$renderer2, {
          variant: "secondary",
          class: "bg-primary/10 text-[10px] font-medium text-primary",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(tag)}`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]--></div>`);
    } else if (evidence.activities_reviewed === true) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<span class="text-[11px] italic text-muted-foreground">Reviewed · no activities selected</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-[11px] italic text-muted-foreground">Unreported</span>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (activityIds().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="break-all font-mono text-[10px] text-muted-foreground">Activity IDs: ${escape_html(activityIds().join(", "))}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div> `);
    if (evidence?.counselor_notes) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="mt-2 border-t border-border/30 pt-2 text-xs text-muted-foreground"><span class="font-semibold text-foreground">Counselor notes:</span> ${escape_html(evidence.counselor_notes)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if ((evidence?.aptitude_scores ?? []).length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<details class="mt-2.5 border-t border-border/30 pt-2"><summary class="cursor-pointer text-[10px] font-semibold uppercase text-muted-foreground">Aptitude records · ${escape_html(evidence.aptitude_scores.length)} recorded</summary> <div class="mt-2 overflow-x-auto"><table class="w-full min-w-[22rem] text-left text-[11px]"><thead><tr class="border-b border-border/40 text-muted-foreground"><th class="py-1 pr-3 font-medium">Catalog area</th><th class="py-1 pr-3 font-medium">Source key</th><th class="py-1 pr-3 font-medium">Recorded value</th><th class="py-1 font-medium">Raw value</th></tr></thead><tbody><!--[-->`);
      const each_array_5 = ensure_array_like(evidence.aptitude_scores);
      for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
        let area = each_array_5[$$index_5];
        $$renderer2.push(`<tr class="border-b border-border/20 last:border-0"><td class="py-1.5 pr-3">${escape_html(area.area_name)}</td><td class="py-1.5 pr-3 font-mono">${escape_html(area.area_code)}</td><td class="py-1.5 pr-3 font-mono tabular-nums">${escape_html(area.converted_label ?? area.score ?? "—")}</td><td class="py-1.5 font-mono tabular-nums">${escape_html(area.raw_score ?? "—")}</td></tr>`);
      }
      $$renderer2.push(`<!--]--></tbody></table></div></details>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function SupplementalEvidenceModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const VALID_PARENTS = [
      "riasec_r",
      "riasec_i",
      "riasec_a",
      "riasec_s",
      "riasec_e",
      "riasec_c"
    ];
    const PARENT_LABELS = {
      riasec_r: "Realistic",
      riasec_i: "Investigative",
      riasec_a: "Artistic",
      riasec_s: "Social",
      riasec_e: "Enterprising",
      riasec_c: "Conventional"
    };
    let {
      open = false,
      attemptId = null
    } = $$props;
    const form = useForm({
      subject_grades: {},
      evidence_source: "physical_form_138",
      vocational_activities: [],
      activities_reviewed: false,
      counselor_notes: "",
      benchmark_activation_id: null,
      schema_digest: null,
      idempotency_key: newIdempotencyKey()
    });
    let subjectGrades = {};
    let evidenceSource = "";
    let vocationalActivities = [];
    let activitiesReviewed = false;
    let counselorNotes = "";
    const groupedActivities = derived(() => VALID_PARENTS.map((parent) => ({
      parent,
      label: PARENT_LABELS[parent],
      activities: [].filter((activity) => activity.parent === parent)
    })));
    if (open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 sm:p-6" role="presentation"><div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl" role="dialog" aria-modal="true" aria-labelledby="supplemental-evidence-title" tabindex="-1"><header class="flex items-start justify-between gap-4 border-b border-border/60 px-5 py-4"><div><h2 id="supplemental-evidence-title" class="text-base font-semibold tracking-tight">Record Supplemental Evidence</h2> <p class="mt-1 text-sm text-muted-foreground">Record reviewed source-bound subject grades and vocational activities in this consultation.</p></div> <button type="button" class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Close supplemental evidence"${attr("disabled", form.processing, true)}>`);
      X($$renderer2, { class: "size-4" });
      $$renderer2.push(`<!----></button></header> <form class="flex min-h-0 flex-1 flex-col"><div class="space-y-5 overflow-y-auto px-5 py-4">`);
      {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p role="alert" class="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">Approved subject and activity definitions are unavailable or invalid. Evidence cannot be recorded until the current schema is available.</p>`);
      }
      $$renderer2.push(`<!--]--> <fieldset class="space-y-3"${attr("disabled", true, true)}><legend class="text-sm font-semibold">Reviewed subject fields</legend> <div class="grid gap-3 sm:grid-cols-2">`);
      const each_array = ensure_array_like([]);
      if (each_array.length !== 0) {
        $$renderer2.push("<!--[-->");
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let subject = each_array[$$index];
          $$renderer2.push(`<div class="space-y-1.5"><label${attr("for", `evidence-${subject.key}`)} class="text-sm font-medium">${escape_html(subject.label)}</label> <div class="flex items-center gap-2"><input${attr("id", `evidence-${subject.key}`)} type="number"${attr("min", subject.source_kind === "approved_intake_binding" ? subject.min_value : void 0)}${attr("max", subject.source_kind === "approved_intake_binding" ? subject.max_value : void 0)} step="any"${attr("value", subjectGrades[subject.key] ?? "")}${attr("aria-invalid", Boolean(form.errors[`subject_grades.${subject.key}`]))}${attr("aria-describedby", `evidence-unit-${subject.key}`)} class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"/> `);
          if (subject.source_kind === "frozen_legacy") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span${attr("id", `evidence-unit-${subject.key}`)} class="text-xs text-muted-foreground">Unit unverified</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<span${attr("id", `evidence-unit-${subject.key}`)} class="text-xs text-muted-foreground">${escape_html(subject.unit)}</span>`);
          }
          $$renderer2.push(`<!--]--></div> <p class="text-xs text-muted-foreground">`);
          if (subject.source_kind === "frozen_legacy") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`Legacy field · source unit/bounds unverified`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`Source ${escape_html(subject.source_code)} · version ${escape_html(subject.source_version)}`);
          }
          $$renderer2.push(`<!--]--> `);
          if (subject.is_required) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`· required in model exemplars, optional in applicant evidence`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></p> `);
          if (form.errors[`subject_grades.${subject.key}`]) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<p class="text-xs text-destructive">${escape_html(form.errors[`subject_grades.${subject.key}`])}</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        }
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push(`<p class="text-sm text-muted-foreground">No approved subject fields are bound to this active schema.</p>`);
      }
      $$renderer2.push(`<!--]--></div></fieldset> <div class="space-y-1.5"><label for="evidence-source" class="text-sm font-semibold">Evidence source</label> `);
      $$renderer2.select(
        {
          id: "evidence-source",
          value: evidenceSource,
          required: true,
          disabled: true,
          class: "h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs"
        },
        ($$renderer3) => {
          $$renderer3.option({ value: "", disabled: true }, ($$renderer4) => {
            $$renderer4.push(`Select the reviewed source`);
          });
          $$renderer3.option({ value: "physical_form_138" }, ($$renderer4) => {
            $$renderer4.push(`Reviewed physical Form 138 / Report Card`);
          });
          $$renderer3.option({ value: "examinee_statement" }, ($$renderer4) => {
            $$renderer4.push(`Applicant declaration`);
          });
        }
      );
      $$renderer2.push(` `);
      if (form.errors.evidence_source) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-xs text-destructive">${escape_html(form.errors.evidence_source)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <fieldset class="space-y-3"${attr("disabled", true, true)}><legend class="text-sm font-semibold">RIASEC activities</legend> <p class="text-xs text-muted-foreground">Select the activities discussed with the applicant. Mark the review complete even when none were selected.</p> <div class="grid gap-3 sm:grid-cols-2"><!--[-->`);
      const each_array_1 = ensure_array_like(groupedActivities());
      for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
        let group = each_array_1[$$index_2];
        $$renderer2.push(`<div class="rounded-lg border border-border/60 p-3"><h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">${escape_html(group.label)}</h3> <div class="flex flex-wrap gap-1.5"><!--[-->`);
        const each_array_2 = ensure_array_like(group.activities);
        for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
          let activity = each_array_2[$$index_1];
          const selected = vocationalActivities.some((id) => String(id) === String(activity.id));
          $$renderer2.push(`<button type="button"${attr("aria-pressed", selected)}${attr_class(`rounded-full border px-2.5 py-1 text-xs transition-colors ${selected ? "border-primary bg-primary text-primary-foreground" : "border-border bg-background text-foreground hover:bg-muted"}`)}>${escape_html(activity.label)}</button>`);
        }
        $$renderer2.push(`<!--]--></div></div>`);
      }
      $$renderer2.push(`<!--]--></div> <label class="flex items-start gap-2 text-sm"><input type="checkbox"${attr("checked", activitiesReviewed, true)} class="mt-0.5 size-4 rounded border-input"/> <span>I reviewed the applicant’s activities. No selection is a reviewed-empty response.</span></label> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (form.errors.vocational_activities || form.errors.activities_reviewed) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-xs text-destructive">${escape_html(form.errors.vocational_activities ?? form.errors.activities_reviewed)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></fieldset> <div class="space-y-1.5"><label for="counselor-notes" class="text-sm font-semibold">Counselor notes</label> <textarea id="counselor-notes" maxlength="1000" rows="3"${attr("disabled", true, true)} class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Record relevant context from the consultation.">`);
      const $$body = escape_html(counselorNotes);
      if ($$body) {
        $$renderer2.push(`${$$body}`);
      }
      $$renderer2.push(`</textarea> `);
      if (form.errors.counselor_notes) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-xs text-destructive">${escape_html(form.errors.counselor_notes)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div> <footer class="flex justify-end gap-2 border-t border-border/60 px-5 py-3"><button type="button" class="h-9 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted"${attr("disabled", form.processing, true)}>Cancel</button> <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"${attr("disabled", form.processing || !attemptId || true, true)}>${escape_html(form.processing ? "Recording…" : "Record Evidence")}</button></footer></form></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function TabFmConsultationCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { model, attemptId } = $$props;
    let isSupplementalEvidenceOpen = false;
    const activeEvidence = derived(() => model?.applicant_evidence ?? {});
    $$renderer2.push(`<div class="space-y-4">`);
    if (model?.benchmark_context_changed) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-muted-foreground">The displayed result uses an earlier benchmark version. Existing recommendations remain pinned to the version that produced them.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (Card) {
      $$renderer2.push("<!--[-->");
      Card($$renderer2, {
        class: "overflow-hidden border-border/70 shadow-sm",
        children: ($$renderer3) => {
          if (Card_content) {
            $$renderer3.push("<!--[-->");
            Card_content($$renderer3, {
              class: "pt-4",
              children: ($$renderer4) => {
                ApplicantEvidenceBar($$renderer4, {
                  evidence: activeEvidence(),
                  inputSchema: model?.input_schema ?? null,
                  onRecordSupplementalEvidence: () => {
                    isSupplementalEvidenceOpen = true;
                  }
                });
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
    $$renderer2.push(` `);
    SupplementalEvidenceModal($$renderer2, {
      open: isSupplementalEvidenceOpen,
      attemptId,
      baselineEvidence: activeEvidence(),
      inputSchema: model?.input_schema ?? null,
      benchmarkActivationId: model?.evidence_context?.benchmark_activation_id ?? null,
      schemaDigest: model?.evidence_context?.schema_digest ?? null
    });
    $$renderer2.push(`<!----> `);
    TabFmResultView($$renderer2, { model, sourceKind: "active" });
    $$renderer2.push(`<!----></div>`);
  });
}
function YuKeeConsultationCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { model } = $$props;
    if (Card) {
      $$renderer2.push("<!--[-->");
      Card($$renderer2, {
        class: "overflow-hidden border-border/70 shadow-sm",
        children: ($$renderer3) => {
          if (Card_header) {
            $$renderer3.push("<!--[-->");
            Card_header($$renderer3, {
              class: "border-b bg-muted/20 pb-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="flex items-start justify-between gap-3"><div class="min-w-0 flex-1">`);
                if (Card_title) {
                  $$renderer4.push("<!--[-->");
                  Card_title($$renderer4, {
                    class: "text-base font-semibold truncate",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model.label ?? "YuKee / K-Means compatibility")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_description) {
                  $$renderer4.push("<!--[-->");
                  Card_description($$renderer4, {
                    class: "mt-1 text-xs",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model.subtitle ?? "Artifact-compatible cluster archetype and radar profile evidence.")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(`</div> `);
                Badge($$renderer4, {
                  variant: "secondary",
                  class: "shrink-0 text-xs",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Standby · Model Verification`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` `);
          if (Card_content) {
            $$renderer3.push("<!--[-->");
            Card_content($$renderer3, {
              class: "space-y-4 pt-4",
              children: ($$renderer4) => {
                if (model.status === "standby") {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="rounded-xl border border-dashed border-amber-500/30 bg-amber-500/[0.03] p-5 text-center">`);
                  Radar($$renderer4, { class: "mx-auto size-7 text-amber-500/70" });
                  $$renderer4.push(`<!----> <p class="mt-2 text-sm font-semibold text-foreground">The K-Means Radar Slot is on Formal Standby</p> <p class="mt-1 text-xs leading-relaxed text-muted-foreground">Awaiting the verified research model file (<code>.joblib</code>) and cluster parameter manifest.</p></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="rounded-lg border bg-muted/20 p-4 text-xs"><p class="font-medium text-foreground">Cluster: ${escape_html(model.cluster_name ?? "Evaluated Profile")}</p></div>`);
                }
                $$renderer4.push(`<!--]--> <p class="text-xs leading-relaxed text-muted-foreground">${escape_html(model.message)}</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
  });
}
function UnknownEngineCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { model } = $$props;
    if (Card) {
      $$renderer2.push("<!--[-->");
      Card($$renderer2, {
        class: "overflow-hidden border-border/70 shadow-sm",
        children: ($$renderer3) => {
          if (Card_header) {
            $$renderer3.push("<!--[-->");
            Card_header($$renderer3, {
              class: "border-b bg-muted/20 pb-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="flex items-start justify-between gap-3"><div>`);
                if (Card_title) {
                  $$renderer4.push("<!--[-->");
                  Card_title($$renderer4, {
                    class: "text-base font-semibold",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model.label ?? "Program-fit constraints")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_description) {
                  $$renderer4.push("<!--[-->");
                  Card_description($$renderer4, {
                    class: "mt-1 text-xs",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model.subtitle ?? "Transparent rule profile for program-head constraints.")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(`</div> `);
                Badge($$renderer4, {
                  variant: "secondary",
                  class: "shrink-0 font-mono text-xs",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Reserved slot`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` `);
          if (Card_content) {
            $$renderer3.push("<!--[-->");
            Card_content($$renderer3, {
              class: "space-y-3 pt-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="relative flex h-36 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30" role="img" aria-label="Reserved visualization slot with no provider output"><div class="absolute inset-x-8 bottom-5 flex items-end justify-between gap-2 opacity-40" aria-hidden="true"><span class="h-8 w-2 rounded-full bg-primary"></span> <span class="h-16 w-2 rounded-full bg-primary"></span> <span class="h-11 w-2 rounded-full bg-primary"></span> <span class="h-20 w-2 rounded-full bg-primary"></span> <span class="h-14 w-2 rounded-full bg-primary"></span></div> <div class="relative flex flex-col items-center gap-1 text-center">`);
                Badge($$renderer4, {
                  variant: "outline",
                  class: "bg-background/90 font-mono text-[10px]",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Preview fixture`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> <span class="text-xs font-medium">${escape_html(model.status === "result_not_ready" ? "Result not ready" : "No provider configured")}</span></div></div> <p class="text-xs leading-relaxed text-muted-foreground">${escape_html(model.message)}</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
  });
}
function ApplicantAdmissionSupport($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "denied",
      role = null,
      attempt = null,
      support = null,
      online_encounter_id = null,
      online_consultation = null,
      available_programs = [],
      ph_record = null,
      ph_program = null,
      threads = [],
      published_advising_notes = []
    } = $$props;
    const page = usePage();
    const sessionRosterBreadcrumbs = derived(() => {
      if (role === "program_head") {
        return [];
      }
      const sessionId = new URL(page.url, "http://localhost").searchParams.get("session_id");
      if (!sessionId || !/^\d+$/.test(sessionId)) {
        return [];
      }
      return [
        { label: "Consultation", href: "/consultation" },
        {
          label: "Session Roster",
          href: `/consultation/sessions/${sessionId}`
        }
      ];
    });
    let activeTab = "profile";
    let decisionDialogOpen = false;
    let lookupDialogOpen = false;
    let followUpDialogOpen = false;
    let newThreadDialogOpen = false;
    let replyBodies = {};
    let newThreadProgramCode = ph_program?.code ?? available_programs[0]?.code ?? "";
    let newThreadSubject = "";
    let threadPagePending = {};
    let noteUseStates = {};
    async function recordNoteUsed(note) {
      const attemptId = attempt?.id;
      const stateKey = `${attemptId}:${note.revision_id}`;
      const previous = noteUseStates[stateKey];
      if (!note.can_record_use || previous?.busy || previous?.receipt || note.usage_receipt) return;
      const key = previous?.key ?? newIdempotencyKey();
      noteUseStates[stateKey] = { key, busy: true, error: "", receipt: null };
      try {
        const response = await governanceRequest(governanceRoute("setup.admission-support.advising-note.use", { attempt: attemptId }), { source_revision_id: note.revision_id, idempotency_key: key });
        if (!response?.usage?.id || Number(response.usage.revision_id) !== Number(note.revision_id) || Number(response.usage.admission_attempt_id) !== Number(attemptId)) {
          throw new Error("The note-use receipt could not be confirmed. Retry the same request.");
        }
        noteUseStates[stateKey] = { key, busy: false, error: "", receipt: response.usage };
      } catch (error) {
        noteUseStates[stateKey] = {
          key,
          busy: false,
          error: governanceError(error),
          receipt: null
        };
      }
    }
    function threadPageParam(threadId) {
      return `thread_messages_${threadId}_page`;
    }
    function currentPageUrl() {
      const currentUrl = new URL(page.url, window.location.origin);
      return `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`;
    }
    function visitThreadPage(threadId, targetPage) {
      const parameter = threadPageParam(threadId);
      if (threadPagePending[threadId]) return;
      const targetUrl = new URL(currentPageUrl(), window.location.origin);
      targetUrl.searchParams.set(parameter, String(targetPage));
      threadPagePending[threadId] = true;
      router.get(`${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`, {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
          threadPagePending[threadId] = false;
        }
      });
    }
    function programTitle(code) {
      if (!code) return "—";
      const found = (available_programs ?? []).find((p) => p.code === code);
      return found ? `[${found.code}] ${found.name}` : code;
    }
    const decisionForm = useForm({
      decision_type: "approve",
      target_program_code: "",
      referral_reason: "",
      notes: "",
      idempotency_key: newIdempotencyKey()
    });
    let decisionClientError = "";
    let decisionAdvisedProgram = "";
    let decisionAlternates = [];
    const p1Code = derived(() => support?.preferences?.[0] ?? "");
    function handleDecisionDialogChange(open) {
      if (open && !decisionDialogOpen) {
        const preferredCode = p1Code() || support?.program_pool?.default || "";
        decisionAdvisedProgram = preferredCode;
        decisionAlternates = [];
        decisionForm.decision_type = "approve";
        decisionForm.target_program_code = "";
        decisionForm.referral_reason = "";
        decisionForm.notes = "";
        decisionForm.idempotency_key = newIdempotencyKey();
        decisionClientError = "";
        decisionForm.clearErrors();
      }
      decisionDialogOpen = open;
    }
    function decisionPoolOptionError(index, field) {
      return decisionForm.errors[`pool_options.${index}.${field}`] ?? "";
    }
    function programQuotaLabel(program) {
      const percentageValue = program.quota_percentage ?? program.fullness_percentage;
      const percentage = Number(percentageValue);
      if (percentageValue !== null && percentageValue !== void 0 && percentageValue !== "" && Number.isFinite(percentage)) {
        return `${Math.min(100, Math.max(0, Math.round(percentage)))}% full`;
      }
      const capacity = Number(program.quota_capacity ?? program.capacity);
      const used = Number(program.quota_used ?? program.enrolled_count ?? program.enrollment_count);
      if (Number.isFinite(capacity) && capacity > 0 && Number.isFinite(used)) {
        return `${Math.min(100, Math.max(0, Math.round(used / capacity * 100)))}% full`;
      }
      return "Quota status not projected";
    }
    function programQuotaIsProjected(program) {
      const percentageValue = program.quota_percentage ?? program.fullness_percentage;
      const percentage = Number(percentageValue);
      if (percentageValue !== null && percentageValue !== void 0 && percentageValue !== "" && Number.isFinite(percentage)) {
        return true;
      }
      const capacity = Number(program.quota_capacity ?? program.capacity);
      const used = Number(program.quota_used ?? program.enrolled_count ?? program.enrollment_count);
      return Number.isFinite(capacity) && capacity > 0 && Number.isFinite(used);
    }
    const consultationHistory = derived(() => {
      const history = Array.isArray(support?.consultation_history) ? [...support.consultation_history] : (() => {
        const evidence = support?.consultation_evidence;
        return evidence?.recorded_at || evidence?.decision_type || evidence?.notes ? [evidence] : [];
      })();
      return history.sort((first, second) => {
        const firstDate = Date.parse(first.recorded_at ?? first.created_at ?? "");
        const secondDate = Date.parse(second.recorded_at ?? second.created_at ?? "");
        return (Number.isNaN(firstDate) ? 0 : firstDate) - (Number.isNaN(secondDate) ? 0 : secondDate);
      });
    });
    function removeDecisionAlternate(index) {
      decisionAlternates = decisionAlternates.filter((_, alternateIndex) => alternateIndex !== index);
    }
    const followUpForm = useForm({
      program_code: "",
      reason: "",
      idempotency_key: newIdempotencyKey()
    });
    function submitFollowUp() {
      if (!attempt?.id) return;
      if (!followUpForm.program_code || !followUpForm.reason.trim()) {
        return;
      }
      followUpForm.idempotency_key = newIdempotencyKey();
      followUpForm.post(
        typeof route === "function" ? route("program-follow-up.open", { attempt: attempt.id }) : `/consultation/${attempt.id}/follow-up`,
        {
          preserveScroll: true,
          onSuccess: () => followUpDialogOpen = false
        }
      );
    }
    function submitDecision() {
      if (!attempt?.id || role !== "guidance") return;
      const decisionType = decisionForm.decision_type;
      const advisesProgram = ["approve", "modify", "override"].includes(decisionType);
      const primaryProgram = decisionType === "approve" ? p1Code() || decisionAdvisedProgram : decisionAdvisedProgram;
      const notesRequired = ["override", "defer"].includes(decisionType);
      if (advisesProgram && !primaryProgram) {
        decisionClientError = "Select a recommended degree program before submitting.";
        return;
      }
      if (decisionType === "refer" && (!decisionForm.target_program_code || !decisionForm.referral_reason.trim())) {
        decisionClientError = "Select a target program and enter a referral reason before submitting.";
        return;
      }
      if (notesRequired && !decisionForm.notes.trim()) {
        decisionClientError = decisionType === "override" ? "Enter the institutional override justification before submitting." : "Enter the pending items or reason for deferral before submitting.";
        return;
      }
      decisionClientError = "";
      const primaryIsPreference = (support?.preferences ?? []).includes(primaryProgram);
      const payload = {
        decision_type: decisionType,
        idempotency_key: decisionForm.idempotency_key,
        ...online_encounter_id !== null ? { encounter_id: online_encounter_id } : {},
        ...advisesProgram ? {
          default_program_code: primaryProgram,
          pool_options: [
            {
              program_code: primaryProgram,
              display_order: 1,
              basis: primaryIsPreference ? "preference" : "guidance_option"
            },
            ...decisionAlternates.filter((programCode) => programCode && programCode !== primaryProgram).map((programCode, index) => ({
              program_code: programCode,
              display_order: index + 2,
              basis: "guidance_option"
            }))
          ],
          pool_reason: (decisionForm.notes || "Consultation advising decision").slice(0, 500)
        } : {},
        ...decisionType === "refer" ? {
          target_program_code: decisionForm.target_program_code,
          referral_reason: decisionForm.referral_reason
        } : {},
        ...decisionType === "defer" || decisionType === "override" ? { notes: decisionForm.notes } : decisionType === "approve" || decisionType === "modify" ? { notes: decisionForm.notes } : {}
      };
      decisionForm.transform(() => payload).post(
        typeof route === "function" ? route("consultation.decision", { attempt: attempt.id }) : `/consultation/${attempt.id}/decision`,
        {
          preserveScroll: true,
          onSuccess: () => {
            decisionDialogOpen = false;
          }
        }
      );
    }
    const readinessLabels = {
      accepted: "Application accepted",
      exam_completed: "Assessment completed",
      grading_finalized: "Result finalized",
      result_released: "Result released",
      advised: "Consultation advised"
    };
    function formatScore(value) {
      if (value === null || value === void 0 || value === "") return "—";
      const numeric = Number(value);
      return Number.isNaN(numeric) ? String(value) : numeric.toLocaleString(void 0, { maximumFractionDigits: 1 });
    }
    function formatDate(iso) {
      if (!iso) return null;
      return new Date(iso).toLocaleDateString(void 0, { month: "short", day: "numeric", year: "numeric" });
    }
    function journeySection($$renderer3) {
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          class: "border-primary/20 bg-primary/[0.02]",
          children: ($$renderer4) => {
            if (Card_header) {
              $$renderer4.push("<!--[-->");
              Card_header($$renderer4, {
                class: "pb-3",
                children: ($$renderer5) => {
                  if (Card_title) {
                    $$renderer5.push("<!--[-->");
                    Card_title($$renderer5, {
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Applicant journey`);
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
                        $$renderer6.push(`<!---->One authoritative view of the admission path.`);
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
                  $$renderer5.push(`<ol class="space-y-3"><!--[-->`);
                  const each_array = ensure_array_like(Object.entries(readinessLabels));
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let [key, label] = each_array[$$index];
                    $$renderer5.push(`<li class="flex items-center gap-2 text-sm">`);
                    if (support?.readiness?.[key]) {
                      $$renderer5.push("<!--[0-->");
                      Circle_check($$renderer5, { class: "size-4 shrink-0 text-emerald-600" });
                      $$renderer5.push(`<!----> <span class="min-w-0 flex-1">${escape_html(label)}</span> `);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "shrink-0 border-emerald-300 bg-emerald-50 text-emerald-700",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Complete`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!---->`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                      $$renderer5.push(`<span class="size-4 shrink-0 rounded-full border border-muted-foreground/40"></span> <span class="min-w-0 flex-1 text-muted-foreground">${escape_html(label)}</span> `);
                      Badge($$renderer5, {
                        variant: "secondary",
                        class: "shrink-0",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Pending`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!---->`);
                    }
                    $$renderer5.push(`<!--]--></li>`);
                  }
                  $$renderer5.push(`<!--]--></ol>`);
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
    }
    function assessmentSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-6"><div><h2 class="text-lg font-semibold tracking-tight">Profile &amp; Assessment</h2> <p class="text-sm text-muted-foreground">Candidate details, initial course preferences, and released aptitude results.</p></div> `);
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          class: "border-border/80 shadow-xs",
          children: ($$renderer4) => {
            if (Card_header) {
              $$renderer4.push("<!--[-->");
              Card_header($$renderer4, {
                class: "pb-3 border-b border-border/40",
                children: ($$renderer5) => {
                  if (Card_title) {
                    $$renderer5.push("<!--[-->");
                    Card_title($$renderer5, {
                      class: "text-base font-bold text-foreground",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Candidate Demographics`);
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
                      class: "text-xs",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Applicant identity and submitted academic preferences.`);
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
                class: "grid gap-x-8 gap-y-5 pt-4 sm:grid-cols-2 lg:grid-cols-3",
                children: ($$renderer5) => {
                  $$renderer5.push(`<div class="space-y-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Full Name</p> <p class="text-sm font-semibold text-foreground">${escape_html(attempt?.name ?? "—")}</p></div> <div class="space-y-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Reference Code</p> <p class="font-mono text-sm font-bold text-foreground">${escape_html(attempt?.reference_code ?? "—")}</p></div> <div class="space-y-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Sex</p> <p class="text-sm font-medium capitalize text-foreground">${escape_html(attempt?.sex ?? attempt?.identity?.sex ?? "—")}</p></div> <div class="space-y-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Track / Strand</p> <span class="inline-block rounded-md border border-border/80 bg-muted/40 px-2 py-0.5 text-xs font-semibold text-foreground">${escape_html(support?.applicant_evidence?.strand ?? attempt?.strand ?? "General")}</span></div> <div class="space-y-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">High School GWA</p> <div class="flex items-baseline gap-1"><span class="font-mono text-xl font-extrabold tabular-nums tracking-tight text-foreground">${escape_html(formatScore(support?.applicant_evidence?.gwa ?? attempt?.gwa))}</span> <span class="text-xs text-muted-foreground">/ 100</span></div></div> <div class="space-y-1.5 sm:col-span-2 lg:col-span-1"><p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Course Preferences</p> <div class="flex flex-col gap-1.5"><!--[-->`);
                  const each_array_1 = ensure_array_like([0, 1, 2]);
                  for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                    let index = each_array_1[$$index_1];
                    const preference = support?.preferences?.[index];
                    $$renderer5.push(`<div class="flex items-center gap-1.5 text-xs"><span${attr_class(`w-6 shrink-0 font-mono font-bold ${index === 0 ? "text-primary" : "text-muted-foreground"}`)}>P${escape_html(index + 1)}</span> `);
                    if (preference) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<span${attr_class(`truncate font-medium ${index === 0 ? "rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 font-semibold text-primary dark:text-primary-foreground/90" : "text-muted-foreground"}`)}>${escape_html(programTitle(preference))}</span>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                      $$renderer5.push(`<span class="text-muted-foreground/60 italic">—</span>`);
                    }
                    $$renderer5.push(`<!--]--></div>`);
                  }
                  $$renderer5.push(`<!--]--></div></div>`);
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
      $$renderer3.push(` <section class="space-y-3"><div><h3 class="text-base font-semibold tracking-tight">Standardized Aptitude Scores</h3> <p class="text-sm text-muted-foreground">Released assessment scores by aptitude area.</p></div> `);
      if (support?.score_profile) {
        $$renderer3.push("<!--[0-->");
        const series = support.score_profile.series ?? [];
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "border-border/80 shadow-xs",
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  class: "pb-3 border-b border-border/40",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="flex items-center justify-between">`);
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        class: "text-base font-bold text-foreground",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(support.score_profile.title)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    Badge($$renderer5, {
                      variant: "outline",
                      class: "font-mono text-xs",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Released ${escape_html(formatDate(support.score_profile.released_at))}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div> `);
                    if (Card_description) {
                      $$renderer5.push("<!--[-->");
                      Card_description($$renderer5, {
                        class: "text-xs",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Source: ${escape_html(support.score_profile.source)}`);
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
                  class: "p-0",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="overflow-x-auto">`);
                    if (Table) {
                      $$renderer5.push("<!--[-->");
                      Table($$renderer5, {
                        children: ($$renderer6) => {
                          if (Table_header) {
                            $$renderer6.push("<!--[-->");
                            Table_header($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_row) {
                                  $$renderer7.push("<!--[-->");
                                  Table_row($$renderer7, {
                                    class: "hover:bg-transparent",
                                    children: ($$renderer8) => {
                                      if (Table_head) {
                                        $$renderer8.push("<!--[-->");
                                        Table_head($$renderer8, {
                                          class: "w-1/2",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Standardized Aptitude Area`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_head) {
                                        $$renderer8.push("<!--[-->");
                                        Table_head($$renderer8, {
                                          class: "w-1/3",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Performance Spectrum`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_head) {
                                        $$renderer8.push("<!--[-->");
                                        Table_head($$renderer8, {
                                          class: "text-right",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Score`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
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
                          if (Table_body) {
                            $$renderer6.push("<!--[-->");
                            Table_body($$renderer6, {
                              children: ($$renderer7) => {
                                const each_array_2 = ensure_array_like(series);
                                if (each_array_2.length !== 0) {
                                  $$renderer7.push("<!--[-->");
                                  for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                                    let score = each_array_2[$$index_2];
                                    const numScore = Number(score.score);
                                    const scorePct = !isNaN(numScore) ? Math.min(100, Math.max(0, numScore)) : 0;
                                    if (Table_row) {
                                      $$renderer7.push("<!--[-->");
                                      Table_row($$renderer7, {
                                        children: ($$renderer8) => {
                                          if (Table_cell) {
                                            $$renderer8.push("<!--[-->");
                                            Table_cell($$renderer8, {
                                              class: "font-semibold text-foreground text-xs",
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->${escape_html(score.area_name || "Aptitude Area")}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                          $$renderer8.push(` `);
                                          if (Table_cell) {
                                            $$renderer8.push("<!--[-->");
                                            Table_cell($$renderer8, {
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<div class="h-2 w-full rounded-full bg-muted overflow-hidden"><div${attr_class(`h-full rounded-full transition-all duration-300 ${scorePct >= 85 ? "bg-emerald-500" : scorePct >= 75 ? "bg-primary" : "bg-amber-400"}`)}${attr_style(`width: ${stringify(scorePct)}%`)}></div></div>`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                          $$renderer8.push(` `);
                                          if (Table_cell) {
                                            $$renderer8.push("<!--[-->");
                                            Table_cell($$renderer8, {
                                              class: "text-right font-mono text-sm font-bold tabular-nums text-foreground",
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->${escape_html(formatScore(score.score))}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                  }
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  if (Table_row) {
                                    $$renderer7.push("<!--[-->");
                                    Table_row($$renderer7, {
                                      children: ($$renderer8) => {
                                        if (Table_cell) {
                                          $$renderer8.push("<!--[-->");
                                          Table_cell($$renderer8, {
                                            colspan: 3,
                                            class: "py-8 text-center text-muted-foreground text-xs",
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->No aptitude scores are available.`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
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
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(`</div>`);
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
      } else {
        $$renderer3.push("<!--[-1-->");
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            children: ($$renderer4) => {
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  class: "py-8 text-center text-sm text-muted-foreground",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->No released score profile available yet for this applicant.`);
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
      }
      $$renderer3.push(`<!--]--></section></section>`);
    }
    function publishedNotesSection($$renderer3) {
      if (published_advising_notes.length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="space-y-3" aria-label="Published program advising notes"><h3 class="text-base font-semibold tracking-tight">Program advising notes</h3> <!--[-->`);
        const each_array_3 = ensure_array_like(published_advising_notes);
        for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
          let note = each_array_3[$$index_3];
          const usageState = noteUseStates[`${attempt?.id}:${note.revision_id}`];
          const receipt = usageState?.receipt ?? note.usage_receipt;
          $$renderer3.push(`<div class="space-y-2 rounded-md border border-border px-4 py-3"><div class="flex flex-wrap items-baseline justify-between gap-2"><h4 class="text-sm font-semibold">${escape_html(note.program_code)} · ${escape_html(note.program_name)}</h4> <a${attr("href", note.details_url)} class="text-sm text-primary underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Note details</a></div> <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">${escape_html(note.content)}</p> <p class="text-xs text-muted-foreground">Revision ${escape_html(note.version)} · Author ${escape_html(note.author_name)} · Published by ${escape_html(note.publisher_name)} <time${attr("datetime", note.published_at)}>· ${escape_html(formatDate(note.published_at))}</time></p> <div class="flex flex-wrap items-center gap-2">`);
          if (receipt) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p role="status" class="text-xs text-muted-foreground">Note use recorded · receipt #${escape_html(receipt.id)} · revision ${escape_html(note.version)} · ${escape_html(formatDate(receipt.used_at))}</p>`);
          } else if (note.can_record_use) {
            $$renderer3.push("<!--[1-->");
            Button($$renderer3, {
              size: "sm",
              variant: "outline",
              disabled: usageState?.busy,
              onclick: () => recordNoteUsed(note),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(usageState?.busy ? "Recording…" : "Record note used")}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <span class="text-xs text-muted-foreground">Advisory only; recording use does not change the decision.</span></div> `);
          if (usageState?.error) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(usageState.error)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]-->`);
    }
    function evidenceSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Preferences &amp; consultation evidence</h2> <p class="text-sm text-muted-foreground">Applicant inputs and human consultation evidence stay separate.</p></div> <div class="grid gap-4 md:grid-cols-2">`);
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          children: ($$renderer4) => {
            if (Card_header) {
              $$renderer4.push("<!--[-->");
              Card_header($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_title) {
                    $$renderer5.push("<!--[-->");
                    Card_title($$renderer5, {
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Applicant Course Preferences`);
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
                  if (support?.preferences?.length) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<ol class="list-inside list-decimal space-y-2 text-sm font-medium"><!--[-->`);
                    const each_array_4 = ensure_array_like(support.preferences);
                    for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                      let p = each_array_4[$$index_4];
                      $$renderer5.push(`<li>${escape_html(programTitle(p))}</li>`);
                    }
                    $$renderer5.push(`<!--]--></ol>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<p class="text-sm text-muted-foreground">No program preferences recorded.</p>`);
                  }
                  $$renderer5.push(`<!--]-->`);
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
      $$renderer3.push(` `);
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          children: ($$renderer4) => {
            if (Card_header) {
              $$renderer4.push("<!--[-->");
              Card_header($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_title) {
                    $$renderer5.push("<!--[-->");
                    Card_title($$renderer5, {
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Structured Consultation Evidence`);
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
                class: "space-y-3 text-sm",
                children: ($$renderer5) => {
                  $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-muted-foreground">Decision Status:</span> `);
                  if (support?.consultation_evidence?.decision_type) {
                    $$renderer5.push("<!--[0-->");
                    Badge($$renderer5, {
                      variant: "default",
                      class: "uppercase",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(support.consultation_evidence.decision_type)}`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Badge($$renderer5, {
                      variant: "outline",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Not recorded`);
                      },
                      $$slots: { default: true }
                    });
                  }
                  $$renderer5.push(`<!--]--></div> `);
                  if (support?.consultation_evidence?.recorded_by) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-muted-foreground">Recorded By:</span> <span class="font-medium">${escape_html(support.consultation_evidence.recorded_by)}</span></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (support?.consultation_evidence?.recorded_at) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-muted-foreground">Recorded On:</span> <span>${escape_html(formatDate(support.consultation_evidence.recorded_at))}</span></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (support?.consultation_evidence?.notes) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="mt-2 rounded-md bg-muted/50 p-2.5 text-xs text-muted-foreground"><p class="font-medium text-foreground">Notes:</p> <p class="mt-0.5">${escape_html(support.consultation_evidence.notes)}</p></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
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
      $$renderer3.push(`</div> `);
      publishedNotesSection($$renderer3);
      $$renderer3.push(`<!----></section>`);
    }
    function analysisSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold tracking-tight">Analysis &amp; Evidence</h2> <p class="text-sm text-muted-foreground">Independent AI / decision-support visualization slots. Models advise — human counselors decide.</p></div> `);
      if (support?.analysis?.status === "evaluated") {
        $$renderer3.push("<!--[0-->");
        Badge($$renderer3, {
          variant: "outline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Advisory · Non-authoritative`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div${attr_class(`grid gap-4 ${(support?.models ?? []).length > 1 ? "xl:grid-cols-2" : ""}`)}><!--[-->`);
      const each_array_5 = ensure_array_like(support?.models ?? []);
      for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
        let m = each_array_5[$$index_5];
        if (m.slot === "tabfm_pairwise") {
          $$renderer3.push("<!--[0-->");
          TabFmConsultationCard($$renderer3, { model: m, attemptId: attempt.id });
        } else if (m.slot === "yu_kee") {
          $$renderer3.push("<!--[1-->");
          YuKeeConsultationCard($$renderer3, { model: m });
        } else {
          $$renderer3.push("<!--[-1-->");
          UnknownEngineCard($$renderer3, { model: m });
        }
        $$renderer3.push(`<!--]-->`);
      }
      $$renderer3.push(`<!--]--></div></section>`);
    }
    function followupSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold tracking-tight">Academic Department Referrals</h2> <p class="text-sm text-muted-foreground">Candidate referrals and special waivers dispatched to Department Heads or College Deans.</p></div> `);
      if (role === "guidance") {
        $$renderer3.push("<!--[0-->");
        Button($$renderer3, {
          variant: "outline",
          class: "min-h-[44px] gap-2 font-semibold",
          onclick: () => {
            followUpForm.program_code = available_programs?.[0]?.code || "";
            followUpDialogOpen = true;
          },
          children: ($$renderer4) => {
            Shield_check($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!----> + Refer to Dean / Head`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          children: ($$renderer4) => {
            if (Card_content) {
              $$renderer4.push("<!--[-->");
              Card_content($$renderer4, {
                class: "p-0",
                children: ($$renderer5) => {
                  if (support?.follow_ups?.length) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<ul class="divide-y"><!--[-->`);
                    const each_array_6 = ensure_array_like(support.follow_ups);
                    for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
                      let f = each_array_6[$$index_6];
                      $$renderer5.push(`<li class="flex items-start justify-between gap-3 p-4 text-sm"><div class="min-w-0"><div class="flex items-center gap-2"><span class="font-semibold">${escape_html(programTitle(f.program_code))}</span> `);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "capitalize",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(f.status?.replace(/_/g, " "))}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div> <p class="mt-1 text-xs text-muted-foreground">${escape_html(f.reason)}</p></div> <span class="shrink-0 text-xs text-muted-foreground">${escape_html(formatDate(f.opened_at))}</span></li>`);
                    }
                    $$renderer5.push(`<!--]--></ul>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<p class="py-8 text-center text-sm text-muted-foreground">No open or resolved departmental referrals for this examinee.</p>`);
                  }
                  $$renderer5.push(`<!--]-->`);
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
      $$renderer3.push(`</section>`);
    }
    function logsSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-6">`);
      followupSection($$renderer3);
      $$renderer3.push(`<!----> <section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Chronological Consultation History &amp; Activity Logs</h2> <p class="text-sm text-muted-foreground">Recorded decisions, timestamps, counselor attribution, and notes.</p></div> `);
      if (consultationHistory().length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<ol class="relative space-y-4 border-l pl-5"><!--[-->`);
        const each_array_7 = ensure_array_like(consultationHistory());
        for (let index = 0, $$length = each_array_7.length; index < $$length; index++) {
          let entry = each_array_7[index];
          $$renderer3.push(`<li class="relative"><span class="absolute -left-[1.58rem] top-1.5 size-2.5 rounded-full border-2 border-background bg-primary"></span> `);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              children: ($$renderer4) => {
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "space-y-2 p-4",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2">`);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "capitalize",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html((entry.decision_type ?? entry.action ?? "Consultation activity").replace(/_/g, " "))}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----> `);
                      if (entry.recorded_by || entry.counselor) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<span class="text-sm text-muted-foreground">by ${escape_html(entry.recorded_by ?? entry.counselor)}</span>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></div> <time class="text-xs text-muted-foreground"${attr("datetime", entry.recorded_at ?? entry.created_at ?? void 0)}>${escape_html(entry.recorded_at || entry.created_at ? new Date(entry.recorded_at ?? entry.created_at).toLocaleString() : "Timestamp unavailable")}</time></div> `);
                      if (entry.notes) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<p class="whitespace-pre-wrap text-sm leading-relaxed">${escape_html(entry.notes)}</p>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        $$renderer5.push(`<p class="text-sm text-muted-foreground">No counselor notes recorded.</p>`);
                      }
                      $$renderer5.push(`<!--]-->`);
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
          $$renderer3.push(`</li>`);
        }
        $$renderer3.push(`<!--]--></ol>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            children: ($$renderer4) => {
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  class: "p-6 text-center text-sm text-muted-foreground",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->No consultation decisions or activity logs have been recorded.`);
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
      }
      $$renderer3.push(`<!--]--></section></section>`);
    }
    function recordSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Program Head record</h2> <p class="text-sm text-muted-foreground">Assigned-program visibility is scoped and audited.</p></div> `);
      if (ph_record) {
        $$renderer3.push("<!--[0-->");
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(ph_record.reference_code)} · ${escape_html(ph_record.name)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    Badge($$renderer5, {
                      variant: ph_record.tier === "B" ? "default" : "secondary",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(ph_record.tier === "B" ? "Protected record" : "Summary")}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div> `);
                    if (Card_description) {
                      $$renderer5.push("<!--[-->");
                      Card_description($$renderer5, {
                        children: ($$renderer6) => {
                          if (ph_record.tier === "B") {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`Access reason: ${escape_html(ph_record.access_reason)} · audited`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`${escape_html(ph_record.visibility_note)}`);
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
              $$renderer4.push(` `);
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  class: "space-y-4 text-sm",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<p>Lifecycle: ${escape_html(ph_record.lifecycle ?? "—")} · Selection: ${escape_html(ph_record.selection?.program_code ?? "—")} ${escape_html(ph_record.selection?.status ?? "")}</p> `);
                    if (ph_record.tier === "B") {
                      $$renderer5.push("<!--[0-->");
                      if (Table) {
                        $$renderer5.push("<!--[-->");
                        Table($$renderer5, {
                          children: ($$renderer6) => {
                            if (Table_caption) {
                              $$renderer6.push("<!--[-->");
                              Table_caption($$renderer6, {
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(ph_record.score_profile?.title ?? "Released converted scores")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
                            $$renderer6.push(` `);
                            if (Table_header) {
                              $$renderer6.push("<!--[-->");
                              Table_header($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Table_row) {
                                    $$renderer7.push("<!--[-->");
                                    Table_row($$renderer7, {
                                      children: ($$renderer8) => {
                                        if (Table_head) {
                                          $$renderer8.push("<!--[-->");
                                          Table_head($$renderer8, {
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->Aptitude area`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                        if (Table_head) {
                                          $$renderer8.push("<!--[-->");
                                          Table_head($$renderer8, {
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->Converted score`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
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
                            if (Table_body) {
                              $$renderer6.push("<!--[-->");
                              Table_body($$renderer6, {
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!--[-->`);
                                  const each_array_8 = ensure_array_like(ph_record.score_profile?.series ?? []);
                                  for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                                    let score = each_array_8[$$index_8];
                                    if (Table_row) {
                                      $$renderer7.push("<!--[-->");
                                      Table_row($$renderer7, {
                                        children: ($$renderer8) => {
                                          if (Table_cell) {
                                            $$renderer8.push("<!--[-->");
                                            Table_cell($$renderer8, {
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->${escape_html(score.area_name ?? score.area_code)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                          if (Table_cell) {
                                            $$renderer8.push("<!--[-->");
                                            Table_cell($$renderer8, {
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->${escape_html(score.score)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
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
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` <!--[-->`);
                      const each_array_9 = ensure_array_like(ph_record.recommendations ?? []);
                      for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
                        let recommendation = each_array_9[$$index_9];
                        $$renderer5.push(`<p>${escape_html(recommendation.program_code)}: ${escape_html(recommendation.compatibility_index)}/100 · ${escape_html(recommendation.band)} · advisory only</p>`);
                      }
                      $$renderer5.push(`<!--]-->`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]-->`);
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
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (ph_record?.tier === "B") {
        $$renderer3.push("<!--[0-->");
        publishedNotesSection($$renderer3);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></section>`);
    }
    function threadsSection($$renderer3) {
      $$renderer3.push(`<section class="space-y-4"><div class="flex items-center justify-between"><div><h2 class="text-lg font-semibold tracking-tight">Internal case threads</h2> <p class="text-sm text-muted-foreground">Official Guidance &amp; Department deliberation under RA 10173.</p></div> `);
      Button($$renderer3, {
        size: "sm",
        variant: "outline",
        onclick: () => {
          newThreadDialogOpen = true;
        },
        children: ($$renderer4) => {
          Plus($$renderer4, { class: "mr-1.5 size-3.5" });
          $$renderer4.push(`<!----> Start Case Thread`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> `);
      if (threads?.length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<!--[-->`);
        const each_array_10 = ensure_array_like(threads);
        for (let $$index_11 = 0, $$length = each_array_10.length; $$index_11 < $$length; $$index_11++) {
          let t = each_array_10[$$index_11];
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "overflow-hidden border-border/80",
              children: ($$renderer4) => {
                if (Card_header) {
                  $$renderer4.push("<!--[-->");
                  Card_header($$renderer4, {
                    class: "bg-muted/15 pb-3",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex items-center justify-between gap-2"><div>`);
                      if (Card_title) {
                        $$renderer5.push("<!--[-->");
                        Card_title($$renderer5, {
                          class: "text-base",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(t.subject)}`);
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
                          class: "text-xs",
                          children: ($$renderer6) => {
                            if (t.program_code) {
                              $$renderer6.push("<!--[0-->");
                              Badge($$renderer6, {
                                variant: "outline",
                                class: "font-mono text-[10px] mr-1.5",
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(t.program_code)}`);
                                },
                                $$slots: { default: true }
                              });
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> ${escape_html(t.status)} · ${escape_html(t.messages?.length ?? 0)} messages`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(`</div> `);
                      Badge($$renderer5, {
                        variant: t.status === "open" ? "default" : "secondary",
                        class: "capitalize text-xs",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(t.status)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div>`);
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
                    class: "space-y-3 pt-4",
                    children: ($$renderer5) => {
                      if (t.messages?.length) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="space-y-2.5 max-h-96 overflow-y-auto pr-1"><!--[-->`);
                        const each_array_11 = ensure_array_like(t.messages);
                        for (let $$index_10 = 0, $$length2 = each_array_11.length; $$index_10 < $$length2; $$index_10++) {
                          let m = each_array_11[$$index_10];
                          $$renderer5.push(`<div class="rounded-lg border border-border/60 bg-muted/40 p-3 shadow-2xs"><div class="flex items-center justify-between text-xs text-muted-foreground mb-1"><span class="font-semibold text-foreground">${escape_html(m.sender)}</span> <span>${escape_html(formatDate(m.created_at))}</span></div> <p class="text-sm text-foreground/90 whitespace-pre-wrap">${escape_html(m.body)}</p></div>`);
                        }
                        $$renderer5.push(`<!--]--></div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        $$renderer5.push(`<p class="text-xs italic text-muted-foreground">No messages in this thread yet.</p>`);
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (t.messages_pagination && t.messages_pagination.last_page > 1) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<nav class="flex items-center justify-between gap-3 border-t border-border/60 pt-3"${attr("aria-label", `Message pages for ${t.subject}`)}>`);
                        Button($$renderer5, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          "aria-label": `Previous message page for ${t.subject}`,
                          disabled: threadPagePending[t.id] || t.messages_pagination.current_page <= 1,
                          onclick: () => visitThreadPage(t.id, t.messages_pagination.current_page - 1),
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Previous`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----> <span class="text-xs text-muted-foreground" aria-live="polite">Page ${escape_html(t.messages_pagination.current_page)} of ${escape_html(t.messages_pagination.last_page)}</span> `);
                        Button($$renderer5, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          "aria-label": `Next message page for ${t.subject}`,
                          disabled: threadPagePending[t.id] || t.messages_pagination.current_page >= t.messages_pagination.last_page,
                          onclick: () => visitThreadPage(t.id, t.messages_pagination.current_page + 1),
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Next`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></nav>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (t.status === "open") {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<form class="pt-2 flex gap-2">`);
                        Input($$renderer5, {
                          placeholder: "Type an official deliberation reply...",
                          class: "text-sm min-h-10",
                          "aria-label": `Reply to ${t.subject}`,
                          get value() {
                            return replyBodies[t.id];
                          },
                          set value($$value) {
                            replyBodies[t.id] = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer5.push(`<!----> `);
                        Button($$renderer5, {
                          type: "submit",
                          size: "sm",
                          class: "min-h-10 shrink-0",
                          disabled: !replyBodies[t.id]?.trim() || threadPagePending[t.id],
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Send`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></form>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]-->`);
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
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            children: ($$renderer4) => {
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  class: "p-8 text-center text-sm text-muted-foreground",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<p class="font-medium text-foreground">No internal department messages recorded.</p> <p class="mt-1 text-xs">Guidance Counselors and Program Heads can start an official thread to deliberate referrals, bridging requirements, or cutoff exemptions.</p> `);
                    Button($$renderer5, {
                      size: "sm",
                      variant: "outline",
                      class: "mt-4",
                      onclick: () => {
                        newThreadDialogOpen = true;
                      },
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Start First Deliberation Thread`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!---->`);
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
      }
      $$renderer3.push(`<!--]--></section> `);
      Dialog($$renderer3, {
        open: newThreadDialogOpen,
        onOpenChange: (open) => {
          newThreadDialogOpen = open;
        },
        children: ($$renderer4) => {
          Dialog_content($$renderer4, {
            class: "sm:max-w-md",
            children: ($$renderer5) => {
              Dialog_header($$renderer5, {
                children: ($$renderer6) => {
                  Dialog_title($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Open Deliberation Thread`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Dialog_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Start an official in-system case thread with Department Chairs and Deans.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> <form class="space-y-4 py-2"><div class="space-y-1.5">`);
              Label($$renderer5, {
                for: "thread_program",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Target Degree Program`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              $$renderer5.select(
                {
                  id: "thread_program",
                  value: newThreadProgramCode,
                  class: "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                },
                ($$renderer6) => {
                  $$renderer6.push(`<!--[-->`);
                  const each_array_12 = ensure_array_like(available_programs);
                  for (let $$index_12 = 0, $$length = each_array_12.length; $$index_12 < $$length; $$index_12++) {
                    let p = each_array_12[$$index_12];
                    $$renderer6.option({ value: p.code }, ($$renderer7) => {
                      $$renderer7.push(`[${escape_html(p.code)}] ${escape_html(p.name)}`);
                    });
                  }
                  $$renderer6.push(`<!--]-->`);
                }
              );
              $$renderer5.push(`</div> <div class="space-y-1.5">`);
              Label($$renderer5, {
                for: "thread_subject",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Subject / Case Reason`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Input($$renderer5, {
                id: "thread_subject",
                placeholder: "e.g. Non-traditional strand review, bridging math requirement",
                required: true,
                get value() {
                  return newThreadSubject;
                },
                set value($$value) {
                  newThreadSubject = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div> `);
              Dialog_footer($$renderer5, {
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    type: "button",
                    variant: "outline",
                    onclick: () => {
                      newThreadDialogOpen = false;
                    },
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Cancel`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Button($$renderer6, {
                    type: "submit",
                    disabled: !newThreadSubject.trim(),
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Create Thread`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></form>`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!---->`);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("mzg031", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Admission Support · ${escape_html(attempt?.name ?? "Examinee")}</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [
          {
            label: role === "program_head" ? "My Programs" : "Counselling",
            ...role === "program_head" ? {} : { href: "/consultation/sessions" }
          },
          ...role === "program_head" ? [] : [{ label: "Consultation", href: "/consultation" }],
          ...sessionRosterBreadcrumbs(),
          { label: attempt?.reference_code ?? "Admission support" }
        ],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:p-6 sm:pb-[calc(7rem+env(safe-area-inset-bottom))] lg:pb-6">`);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----> `);
          if (page_state === "ready" && attempt && (role === "guidance" && support || role === "program_head" && ph_record)) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<header class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between"><div><h1 class="font-serif text-3xl font-semibold tracking-tight">${escape_html(attempt.name)}</h1> <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"><span class="font-mono">${escape_html(attempt.reference_code)}</span> <span aria-hidden="true">·</span> <span>Admission Support &amp; Advisory Workspace</span> `);
            if (role === "program_head" && ph_program) {
              $$renderer4.push("<!--[0-->");
              Badge($$renderer4, {
                variant: "outline",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Assigned program: ${escape_html(ph_program.code)}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div></div> `);
            if (role === "guidance") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
              Button($$renderer4, {
                size: "lg",
                class: "hidden min-h-[44px] gap-2 font-semibold shadow-sm lg:inline-flex",
                disabled: !support?.readiness?.grading_finalized,
                onclick: () => handleDecisionDialogChange(true),
                children: ($$renderer5) => {
                  User_check($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> Record Consultation`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (online_consultation?.visible) {
                $$renderer4.push("<!--[0-->");
                if (online_consultation.encounter_url) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<a${attr("href", online_consultation.encounter_url)} class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">Open online consultation</a>`);
                } else if (!online_consultation.can_start) {
                  $$renderer4.push("<!--[1-->");
                  $$renderer4.push(`<span role="note" class="text-xs font-normal text-muted-foreground"${attr("title", online_consultation.reason ?? void 0)}>${escape_html(online_consultation.reason?.startsWith("Online consultation is unavailable:") ? "Online consultation is temporarily unavailable" : online_consultation.reason ?? "Online consultation is currently unavailable")}</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<button type="button" class="text-xs font-normal text-muted-foreground underline-offset-4 hover:text-foreground hover:underline disabled:opacity-60"${attr("disabled", !online_consultation.can_start, true)}${attr("title", online_consultation.reason ?? void 0)}>${escape_html(online_consultation.exception_mode ? "Start online exception" : "Start online consultation")}</button>`);
                }
                $$renderer4.push(`<!--]--> `);
                {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]-->`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></header> `);
            if (role === "guidance" && !support?.readiness?.grading_finalized) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status" aria-live="polite"><p class="font-semibold text-amber-950 dark:text-amber-200">Consultation is not ready</p> <p class="mt-1 text-muted-foreground">Grading must be finalized before this examinee can begin consultation. You can review the applicant record, but consultation decisions may be unavailable until results are ready.</p></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (role === "guidance") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="lg:hidden">`);
              journeySection($$renderer4);
              $$renderer4.push(`<!----></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"><main class="min-w-0">`);
            if (Tabs) {
              $$renderer4.push("<!--[-->");
              Tabs($$renderer4, {
                value: activeTab,
                onValueChange: (value) => activeTab = value,
                children: ($$renderer5) => {
                  if (Tabs_list) {
                    $$renderer5.push("<!--[-->");
                    Tabs_list($$renderer5, {
                      class: "sticky top-0 z-30 mb-6 flex w-full max-w-2xl items-center gap-1 rounded-xl border border-border/70 bg-card/90 p-1 backdrop-blur-md shadow-2xs",
                      children: ($$renderer6) => {
                        if (role === "guidance") {
                          $$renderer6.push("<!--[0-->");
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "profile",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Profile &amp; Assessment`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "analysis",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Recommendations &amp; Analysis`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "logs",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Referrals &amp; Activity Logs`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "threads",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Case Threads`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        } else if (role === "program_head") {
                          $$renderer6.push("<!--[1-->");
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "record",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Record`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              class: "flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60",
                              value: "threads",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Threads`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
                  $$renderer5.push(` `);
                  if (role === "guidance") {
                    $$renderer5.push("<!--[0-->");
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "profile",
                        children: ($$renderer6) => {
                          assessmentSection($$renderer6);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "analysis",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="space-y-6">`);
                          evidenceSection($$renderer6);
                          $$renderer6.push(`<!----> `);
                          analysisSection($$renderer6);
                          $$renderer6.push(`<!----></div>`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "logs",
                        children: ($$renderer6) => {
                          logsSection($$renderer6);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "threads",
                        children: ($$renderer6) => {
                          threadsSection($$renderer6);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                  } else if (role === "program_head") {
                    $$renderer5.push("<!--[1-->");
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "record",
                        children: ($$renderer6) => {
                          recordSection($$renderer6);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Tabs_content) {
                      $$renderer5.push("<!--[-->");
                      Tabs_content($$renderer5, {
                        value: "threads",
                        children: ($$renderer6) => {
                          threadsSection($$renderer6);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(`</main> `);
            if (role === "guidance") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<aside class="hidden space-y-4 lg:block lg:sticky lg:top-6 lg:self-start h-fit">`);
              journeySection($$renderer4);
              $$renderer4.push(`<!----> `);
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-border/80 shadow-xs",
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3 border-b border-border/40",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-sm font-bold text-foreground",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Case facts`);
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
                        class: "space-y-3 pt-3 text-xs",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Current Selection</span> <span class="font-semibold text-foreground">${escape_html(programTitle(support?.readiness?.selection?.program_code))}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Advised Course</span> <span class="font-semibold text-primary">${escape_html(programTitle(support?.program_pool?.default))}</span></div>`);
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
              $$renderer4.push(`</aside>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (role === "guidance") {
              $$renderer4.push("<!--[0-->");
              ConsultationMobileDock($$renderer4, {
                mode: "detail",
                onSearch: () => lookupDialogOpen = true,
                listHref: "/consultation",
                onRecord: () => handleDecisionDialogChange(true),
                recordDisabled: !support?.readiness?.grading_finalized
              });
              $$renderer4.push(`<!----> `);
              ApplicantLookupDialog($$renderer4, {
                open: lookupDialogOpen,
                onOpenChange: (open) => lookupDialogOpen = open
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (role === "guidance") {
              $$renderer4.push("<!--[0-->");
              Dialog($$renderer4, {
                open: decisionDialogOpen,
                onOpenChange: handleDecisionDialogChange,
                children: ($$renderer5) => {
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-3xl max-h-[90vh] flex flex-col p-0",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="overflow-y-auto p-6 space-y-6">`);
                      Dialog_header($$renderer6, {
                        children: ($$renderer7) => {
                          Dialog_title($$renderer7, {
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Record Consultation Decision`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!----> `);
                          Dialog_description($$renderer7, {
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Choose the advising outcome for ${escape_html(attempt.name)}. This action and any program recommendations are recorded together.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> <section class="space-y-3"><h3 class="text-sm font-semibold">Choose an action</h3> <div class="flex gap-2.5 overflow-x-auto pb-1"><button type="button"${attr("aria-pressed", ["approve", "modify", "override"].includes(decisionForm.decision_type))}${attr_class(`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${["approve", "modify", "override"].includes(decisionForm.decision_type) ? "border-primary bg-primary/5 text-primary" : "hover:border-primary/50 hover:bg-muted/40"}`)}><span aria-hidden="true">★</span> Endorse Program</button> <button type="button"${attr("aria-pressed", decisionForm.decision_type === "refer")}${attr_class(`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${decisionForm.decision_type === "refer" ? "border-primary bg-primary/5 text-primary" : "hover:border-primary/50 hover:bg-muted/40"}`)}>`);
                      Building_2($$renderer6, { class: "size-4" });
                      $$renderer6.push(`<!----> Refer to Dean</button> <button type="button"${attr("aria-pressed", decisionForm.decision_type === "defer")}${attr_class(`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${decisionForm.decision_type === "defer" ? "border-primary bg-primary/5 text-primary" : "hover:border-primary/50 hover:bg-muted/40"}`)}>`);
                      Clock($$renderer6, { class: "size-4" });
                      $$renderer6.push(`<!----> Defer Session</button></div></section> `);
                      if (["approve", "modify", "override"].includes(decisionForm.decision_type)) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<section class="space-y-4 rounded-xl border bg-muted/20 p-4"><div><h3 class="font-semibold">Recommended Program</h3> <p class="mt-1 text-sm text-muted-foreground">Choose the program to endorse. Selecting P1 approves the first preference; another program records a modification.</p></div> <div class="space-y-2"><!--[-->`);
                        const each_array_13 = ensure_array_like(available_programs);
                        for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
                          let prog = each_array_13[$$index_13];
                          $$renderer6.push(`<button type="button"${attr("aria-pressed", decisionAdvisedProgram === prog.code)}${attr_class(`flex w-full flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3 text-left transition-colors ${decisionAdvisedProgram === prog.code ? "border-primary ring-2 ring-primary/20" : "hover:border-primary/50"}`)}><span class="min-w-0"><span class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-semibold">${escape_html(prog.code)}</span> `);
                          if (prog.code === p1Code()) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "default",
                              class: "text-[10px]",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->★ 1st Preference`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></span> <span class="mt-1 block text-sm font-medium">${escape_html(prog.name)}</span></span> <span class="flex shrink-0 flex-col items-end gap-1 text-xs font-medium text-muted-foreground" title="Quota availability is not projected here. Saving enforces authoritative program capacity."><span class="flex items-center gap-1.5"><span aria-hidden="true" class="text-primary">◐</span> ${escape_html(programQuotaLabel(prog))}</span> `);
                          if (!programQuotaIsProjected(prog)) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<span class="max-w-48 text-right text-[10px] font-normal">Capacity is enforced when you save.</span>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></span></button>`);
                        }
                        $$renderer6.push(`<!--]--></div> `);
                        if (decisionForm.errors.default_program_code) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.default_program_code)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (decisionPoolOptionError(0, "program_code")) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionPoolOptionError(0, "program_code"))}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (decisionPoolOptionError(0, "basis")) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-xs text-destructive" role="alert">${escape_html(decisionPoolOptionError(0, "basis"))}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (decisionPoolOptionError(0, "display_order")) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-xs text-destructive" role="alert">${escape_html(decisionPoolOptionError(0, "display_order"))}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> <label class="flex cursor-pointer items-start gap-3 rounded-lg border bg-background p-3 text-sm"><input type="checkbox" class="mt-0.5 size-4 accent-primary"${attr("checked", decisionForm.decision_type === "override", true)}/> <span><span class="font-semibold">Grant Institutional Policy Override</span> <span class="mt-0.5 block text-xs text-muted-foreground">A written justification is required. Quota and program capacity still apply.</span></span></label> `);
                        if (decisionForm.decision_type === "override") {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="space-y-2">`);
                          Label($$renderer6, {
                            for: "decision-notes",
                            class: "font-semibold",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Override Justification *`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Textarea($$renderer6, {
                            id: "decision-notes",
                            oninput: () => decisionClientError = "",
                            placeholder: "Document the institutional policy basis and justification...",
                            rows: "3",
                            required: true,
                            get value() {
                              return decisionForm.notes;
                            },
                            set value($$value) {
                              decisionForm.notes = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----> `);
                          if (decisionForm.errors.notes) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.notes)}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        } else if (decisionForm.decision_type === "modify") {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<div class="space-y-2">`);
                          Label($$renderer6, {
                            for: "decision-notes",
                            class: "font-semibold",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Consultation Notes &amp; Findings`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Textarea($$renderer6, {
                            id: "decision-notes",
                            placeholder: "Record student strengths, career considerations, or advising context...",
                            rows: "3",
                            get value() {
                              return decisionForm.notes;
                            },
                            set value($$value) {
                              decisionForm.notes = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----> `);
                          if (decisionForm.errors.notes) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.notes)}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> <div class="space-y-3"><button type="button" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"${attr("disabled", decisionAlternates.length >= Math.max(0, available_programs.length - 1), true)}>`);
                        Plus($$renderer6, { class: "size-4" });
                        $$renderer6.push(`<!----> Add Alternate Degree Option</button> <!--[-->`);
                        const each_array_14 = ensure_array_like(decisionAlternates);
                        for (let index = 0, $$length = each_array_14.length; index < $$length; index++) {
                          each_array_14[index];
                          $$renderer6.push(`<div class="flex items-center gap-2">`);
                          $$renderer6.select(
                            {
                              "aria-label": `Alternate degree option ${index + 1}`,
                              value: decisionAlternates[index],
                              class: "flex h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            },
                            ($$renderer7) => {
                              $$renderer7.push(`<!--[-->`);
                              const each_array_15 = ensure_array_like(available_programs);
                              for (let $$index_14 = 0, $$length2 = each_array_15.length; $$index_14 < $$length2; $$index_14++) {
                                let prog = each_array_15[$$index_14];
                                if (prog.code !== decisionAdvisedProgram && !decisionAlternates.some((code, alternateIndex) => alternateIndex !== index && code === prog.code)) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.option({ value: prog.code }, ($$renderer8) => {
                                    $$renderer8.push(`[${escape_html(prog.code)}] ${escape_html(prog.name)}`);
                                  });
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]-->`);
                              }
                              $$renderer7.push(`<!--]-->`);
                            }
                          );
                          $$renderer6.push(` `);
                          if (decisionPoolOptionError(index + 1, "program_code")) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-xs text-destructive" role="alert">${escape_html(decisionPoolOptionError(index + 1, "program_code"))}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            size: "icon",
                            "aria-label": "Remove alternate program",
                            onclick: () => removeDecisionAlternate(index),
                            children: ($$renderer7) => {
                              Trash_2($$renderer7, { class: "size-4" });
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div>`);
                        }
                        $$renderer6.push(`<!--]--></div></section>`);
                      } else if (decisionForm.decision_type === "refer") {
                        $$renderer6.push("<!--[1-->");
                        $$renderer6.push(`<section class="space-y-4 rounded-xl border border-purple-200 bg-purple-50/30 p-4 dark:border-purple-900 dark:bg-purple-950/20"><div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "target-program-code",
                          class: "font-semibold",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Target Academic Department Program *`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        $$renderer6.select(
                          {
                            id: "target-program-code",
                            value: decisionForm.target_program_code,
                            onchange: () => decisionClientError = "",
                            required: true,
                            class: "flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                          },
                          ($$renderer7) => {
                            $$renderer7.option({ value: "" }, ($$renderer8) => {
                              $$renderer8.push(`Select department program...`);
                            });
                            $$renderer7.push(`<!--[-->`);
                            const each_array_16 = ensure_array_like(available_programs);
                            for (let $$index_16 = 0, $$length = each_array_16.length; $$index_16 < $$length; $$index_16++) {
                              let prog = each_array_16[$$index_16];
                              $$renderer7.option({ value: prog.code }, ($$renderer8) => {
                                $$renderer8.push(`[${escape_html(prog.code)}] ${escape_html(prog.name)}`);
                              });
                            }
                            $$renderer7.push(`<!--]-->`);
                          }
                        );
                        $$renderer6.push(` `);
                        if (decisionForm.errors.target_program_code) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.target_program_code)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "referral-reason",
                          class: "font-semibold",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Referral Reason for Program Head / Dean *`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Textarea($$renderer6, {
                          id: "referral-reason",
                          oninput: () => decisionClientError = "",
                          placeholder: "Explain why department review, quota clearance, or an interview is needed...",
                          rows: "3",
                          required: true,
                          get value() {
                            return decisionForm.referral_reason;
                          },
                          set value($$value) {
                            decisionForm.referral_reason = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----> `);
                        if (decisionForm.errors.referral_reason) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.referral_reason)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div></section>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<section class="space-y-3 rounded-xl border bg-muted/20 p-4"><div class="space-y-2">`);
                        Label($$renderer6, {
                          for: "decision-notes",
                          class: "font-semibold",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Pending Items / Reason for Deferral *`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Textarea($$renderer6, {
                          id: "decision-notes",
                          oninput: () => decisionClientError = "",
                          placeholder: "List the documents or discussion needed before advising can continue...",
                          rows: "3",
                          required: true,
                          get value() {
                            return decisionForm.notes;
                          },
                          set value($$value) {
                            decisionForm.notes = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----> `);
                        if (decisionForm.errors.notes) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive" role="alert">${escape_html(decisionForm.errors.notes)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> <p class="text-sm text-muted-foreground">The student stays in the Guidance queue and is not cleared for enrollment until consultation is completed.</p></section>`);
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionClientError) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm font-medium text-destructive" role="alert">${escape_html(decisionClientError)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.decision_type) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.decision_type)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.default_program_code) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.default_program_code)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.pool_options) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.pool_options)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.target_program_code) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.target_program_code)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.referral_reason) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.referral_reason)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.notes) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(decisionForm.errors.notes)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (decisionForm.errors.decision) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm font-medium text-destructive" role="alert">${escape_html(decisionForm.errors.decision)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      Dialog_footer($$renderer6, {
                        class: "px-6 pb-6",
                        children: ($$renderer7) => {
                          Button($$renderer7, {
                            variant: "outline",
                            onclick: () => decisionDialogOpen = false,
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Cancel`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!----> `);
                          Button($$renderer7, {
                            onclick: submitDecision,
                            disabled: decisionForm.processing,
                            class: "font-semibold",
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Save Decision`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Dialog($$renderer4, {
                open: followUpDialogOpen,
                onOpenChange: (v) => followUpDialogOpen = v,
                children: ($$renderer5) => {
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-md",
                    children: ($$renderer6) => {
                      Dialog_header($$renderer6, {
                        children: ($$renderer7) => {
                          Dialog_title($$renderer7, {
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Refer to Academic Department Head / Dean`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!----> `);
                          Dialog_description($$renderer7, {
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Dispatches an edge case or waiver evaluation to the academic department head. This action cannot be withdrawn by Guidance after opening.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> <div class="space-y-4"><div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "followup-program",
                        class: "font-semibold",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Department Degree Program`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      $$renderer6.select(
                        {
                          id: "followup-program",
                          value: followUpForm.program_code,
                          class: "flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        },
                        ($$renderer7) => {
                          $$renderer7.option({ value: "" }, ($$renderer8) => {
                            $$renderer8.push(`Select academic program...`);
                          });
                          $$renderer7.push(`<!--[-->`);
                          const each_array_17 = ensure_array_like(available_programs);
                          for (let $$index_17 = 0, $$length = each_array_17.length; $$index_17 < $$length; $$index_17++) {
                            let prog = each_array_17[$$index_17];
                            $$renderer7.option({ value: prog.code }, ($$renderer8) => {
                              $$renderer8.push(`[${escape_html(prog.code)}] ${escape_html(prog.name)}`);
                            });
                          }
                          $$renderer7.push(`<!--]-->`);
                        }
                      );
                      $$renderer6.push(`</div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "followup-reason",
                        class: "font-semibold",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Referral Reason &amp; Specific Request`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Textarea($$renderer6, {
                        id: "followup-reason",
                        placeholder: "Explain why this applicant is referred (e.g. Needs interview, quota inquiry, prerequisite waiver)...",
                        rows: "3",
                        get value() {
                          return followUpForm.reason;
                        },
                        set value($$value) {
                          followUpForm.reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (followUpForm.errors.follow_up) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm font-medium text-destructive">${escape_html(followUpForm.errors.follow_up)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      Dialog_footer($$renderer6, {
                        children: ($$renderer7) => {
                          Button($$renderer7, {
                            variant: "outline",
                            onclick: () => followUpDialogOpen = false,
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Cancel`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!----> `);
                          Button($$renderer7, {
                            onclick: submitFollowUp,
                            disabled: followUpForm.processing,
                            class: "font-semibold",
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Send Referral`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div>`);
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
  ApplicantAdmissionSupport as default
};
