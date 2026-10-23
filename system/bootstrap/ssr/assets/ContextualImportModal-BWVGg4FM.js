import { g as sanitize_props, i as spread_props, j as slot, l as bind_props, e as escape_html, a as ensure_array_like, B as Button, d as derived, k as sound } from "../app.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { F as File_upload } from "./file-upload-B9oVsy17.js";
import { U as Users } from "./users-DWlguTa_.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { I as Info } from "./info-C3MCzlQW.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { F as File_spreadsheet } from "./settings-C5xOKpV3.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { router } from "@inertiajs/core";
function Brain_circuit($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"
      }
    ],
    ["path", { "d": "M9 13a4.5 4.5 0 0 0 3-4" }],
    ["path", { "d": "M6.003 5.125A3 3 0 0 0 6.401 6.5" }],
    ["path", { "d": "M3.477 10.896a4 4 0 0 1 .585-.396" }],
    ["path", { "d": "M6 18a4 4 0 0 1-1.967-.516" }],
    ["path", { "d": "M12 13h4" }],
    ["path", { "d": "M12 18h6a2 2 0 0 1 2 2v1" }],
    ["path", { "d": "M12 8h8" }],
    ["path", { "d": "M16 8V5a2 2 0 0 1 2-2" }],
    ["circle", { "cx": "16", "cy": "13", "r": ".5" }],
    ["circle", { "cx": "18", "cy": "3", "r": ".5" }],
    ["circle", { "cx": "20", "cy": "21", "r": ".5" }],
    ["circle", { "cx": "20", "cy": "8", "r": ".5" }]
  ];
  Icon($$renderer, spread_props([
    { name: "brain-circuit" },
    $$sanitized_props,
    {
      /**
       * @component @name BrainCircuit
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgNWEzIDMgMCAxIDAtNS45OTcuMTI1IDQgNCAwIDAgMC0yLjUyNiA1Ljc3IDQgNCAwIDAgMCAuNTU2IDYuNTg4QTQgNCAwIDEgMCAxMiAxOFoiIC8+CiAgPHBhdGggZD0iTTkgMTNhNC41IDQuNSAwIDAgMCAzLTQiIC8+CiAgPHBhdGggZD0iTTYuMDAzIDUuMTI1QTMgMyAwIDAgMCA2LjQwMSA2LjUiIC8+CiAgPHBhdGggZD0iTTMuNDc3IDEwLjg5NmE0IDQgMCAwIDEgLjU4NS0uMzk2IiAvPgogIDxwYXRoIGQ9Ik02IDE4YTQgNCAwIDAgMS0xLjk2Ny0uNTE2IiAvPgogIDxwYXRoIGQ9Ik0xMiAxM2g0IiAvPgogIDxwYXRoIGQ9Ik0xMiAxOGg2YTIgMiAwIDAgMSAyIDJ2MSIgLz4KICA8cGF0aCBkPSJNMTIgOGg4IiAvPgogIDxwYXRoIGQ9Ik0xNiA4VjVhMiAyIDAgMCAxIDItMiIgLz4KICA8Y2lyY2xlIGN4PSIxNiIgY3k9IjEzIiByPSIuNSIgLz4KICA8Y2lyY2xlIGN4PSIxOCIgY3k9IjMiIHI9Ii41IiAvPgogIDxjaXJjbGUgY3g9IjIwIiBjeT0iMjEiIHI9Ii41IiAvPgogIDxjaXJjbGUgY3g9IjIwIiBjeT0iOCIgcj0iLjUiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/brain-circuit
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function ContextualImportModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      importType = "applicants_only",
      // 'applicants_only' | 'scores_only'
      academicCycle = null,
      aptitudeAreas = []
    } = $$props;
    let selectedFile = [];
    let analysis = null;
    let analyzeError = "";
    let submitting = false;
    let submitError = "";
    let activeAptitudeAreas = derived(() => analysis?.aptitude_areas && analysis.aptitude_areas.length > 0 ? analysis.aptitude_areas : aptitudeAreas && aptitudeAreas.length > 0 ? aptitudeAreas : [
      { code: "VERBAL", name: "Verbal Aptitude", max_items: 100 },
      { code: "QUANT", name: "Numerical Aptitude", max_items: 100 },
      { code: "LOGIC", name: "General Ability", max_items: 25 },
      { code: "SPATIAL", name: "Spatial Aptitude", max_items: 25 },
      { code: "MEMORY", name: "Perceptual Aptitude", max_items: 25 },
      { code: "LANGUAGE", name: "Manual Dexterity", max_items: 25 }
    ]);
    const isApplicants = derived(() => importType === "applicants_only");
    const title = derived(() => isApplicants() ? "Import Applicants" : "Import Examinee Aptitude Scores");
    const description = derived(() => isApplicants() ? "Upload applicant spreadsheets, check the columns, then review saved rows before adding applicant records to the active academic cycle." : "Upload examinee scores, check the mapped aptitude columns, then review saved rows before adding scores.");
    const applicantGuideColumns = [
      {
        code: "first_name",
        name: "First Name",
        required: true,
        floor: true,
        note: "Protected floor"
      },
      {
        code: "last_name",
        name: "Last Name",
        required: true,
        floor: true,
        note: "Protected floor"
      },
      {
        code: "birthdate",
        name: "Date of Birth",
        required: true,
        floor: true,
        note: "YYYY-MM-DD or Excel date"
      },
      {
        code: "sex",
        name: "Sex / Gender",
        required: true,
        floor: true,
        note: "Male / Female"
      },
      {
        code: "course_preference_1",
        name: "1st Choice Program",
        required: true,
        floor: true,
        note: "Program code (e.g. BSCS)"
      },
      {
        code: "contact_email",
        name: "Email Address",
        required: true,
        note: "Institution required"
      },
      {
        code: "strand",
        name: "Senior High Strand",
        required: true,
        note: "e.g. STEM, ABM, HUMSS"
      },
      {
        code: "last_school_enrolled",
        name: "Last School",
        required: true,
        note: "School of origin"
      }
    ];
    const isReadyToImport = derived(() => analysis && analysis.total_rows > 0 && (isApplicants() ? (analysis.missing_required?.length ?? 0) === 0 : true) && !analyzeError && !submitting);
    function handleSubmit() {
      const file = Array.isArray(selectedFile) ? selectedFile[0] : selectedFile;
      if (!file || !isReadyToImport()) return;
      submitting = true;
      submitError = "";
      const formData = new FormData();
      formData.append("file", file);
      formData.append("import_type", importType);
      if (academicCycle?.id) {
        formData.append("academic_cycle_id", academicCycle.id);
      }
      router.post("/imports/stage", formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          sound.success();
          open = false;
          selectedFile = [];
          analysis = null;
        },
        onError: (errors) => {
          sound.error();
          submitError = Object.values(errors).flat().join(" ") || "Import failed. Check file rows and format.";
        },
        onFinish: () => {
          submitting = false;
        }
      });
    }
    function handleClose() {
      if (!submitting) {
        open = false;
        selectedFile = [];
        analysis = null;
        analyzeError = "";
        submitError = "";
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          onOpenChange: (v) => {
            if (!v) handleClose();
          },
          get open() {
            return open;
          },
          set open($$value) {
            open = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "border-border bg-card flex max-h-[90vh] w-[94vw] max-w-4xl flex-col rounded-2xl border p-6 shadow-2xl sm:max-w-4xl sm:p-8",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      class: "border-border/60 shrink-0 space-y-1.5 border-b pb-4 text-left",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center gap-3"><div class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">`);
                        if (isApplicants()) {
                          $$renderer6.push("<!--[0-->");
                          Users($$renderer6, { class: "size-5" });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Brain_circuit($$renderer6, { class: "size-5" });
                        }
                        $$renderer6.push(`<!--]--></div> <div class="min-w-0 flex-1">`);
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            class: "text-foreground text-xl font-bold tracking-tight",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(title())}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            class: "text-muted-foreground mt-0.5 text-xs",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(description())}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div> `);
                        if (academicCycle?.label) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: "outline",
                            class: "shrink-0 font-mono text-xs font-normal",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(academicCycle.label)}`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
                  $$renderer5.push(` <div class="grid flex-1 grid-cols-1 gap-6 overflow-y-auto py-4 pr-1 lg:grid-cols-12"><div class="space-y-4 lg:col-span-7">`);
                  File_upload($$renderer5, {
                    label: "Select spreadsheet file",
                    sublabel: "CSV, XLSX, XLS, or TXT up to 25 MB",
                    accept: ".csv,.xlsx,.xls,.txt",
                    maxSize: "25MB",
                    get files() {
                      return selectedFile;
                    },
                    set files($$value) {
                      selectedFile = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----> `);
                  {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (analyzeError) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-3 rounded-xl border p-4 text-xs">`);
                    Triangle_alert($$renderer5, { class: "mt-0.5 size-4 shrink-0" });
                    $$renderer5.push(`<!----> <div><p class="font-bold">Spreadsheet Validation Issue</p> <p class="mt-0.5 leading-relaxed">${escape_html(analyzeError)}</p></div></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (submitError) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-3 rounded-xl border p-4 text-xs">`);
                    Circle_x($$renderer5, { class: "mt-0.5 size-4 shrink-0" });
                    $$renderer5.push(`<!----> <div><p class="font-bold">Import Could Not Complete</p> <p class="mt-0.5 leading-relaxed">${escape_html(submitError)}</p></div></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (analysis && true) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="border-border/80 bg-background space-y-3 rounded-xl border p-4 shadow-xs"><div class="border-border/50 flex items-center justify-between border-b pb-2.5"><span class="text-foreground flex items-center gap-2 text-xs font-bold">`);
                    File_spreadsheet($$renderer5, { class: "text-primary size-4" });
                    $$renderer5.push(`<!----> ${escape_html(analysis.file_name)}</span> <div class="flex items-center gap-2">`);
                    Badge($$renderer5, {
                      variant: "secondary",
                      class: "font-mono text-[11px]",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(analysis.total_rows)} row${escape_html(analysis.total_rows === 1 ? "" : "s")}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----> `);
                    if (analysis.sheets && analysis.sheets.length > 1) {
                      $$renderer5.push("<!--[0-->");
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "font-mono text-[10px]",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(analysis.sheets.length)} sheets`);
                        },
                        $$slots: { default: true }
                      });
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div></div> <div class="grid grid-cols-2 gap-2 text-xs"><!--[-->`);
                    const each_array = ensure_array_like(analysis.checks ?? []);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let check = each_array[$$index];
                      $$renderer5.push(`<div class="bg-muted/30 flex items-center gap-2 rounded-lg px-3 py-2">`);
                      if (check.status === "pass") {
                        $$renderer5.push("<!--[0-->");
                        Circle_check($$renderer5, {
                          class: "size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        Circle_x($$renderer5, { class: "text-destructive size-4 shrink-0" });
                      }
                      $$renderer5.push(`<!--]--> <div class="min-w-0 flex-1"><p class="text-foreground truncate text-[11px] font-semibold">${escape_html(check.label)}</p> <p class="text-muted-foreground truncate text-[10px]">${escape_html(check.detail)}</p></div></div>`);
                    }
                    $$renderer5.push(`<!--]--></div> `);
                    if (analysis.missing_required && analysis.missing_required.length > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300"><p class="font-bold">Missing required columns in spreadsheet:</p> <p class="mt-0.5 font-mono text-[11px]">${escape_html(analysis.missing_required.join(", "))}</p></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (analysis.column_analysis && analysis.column_analysis.length > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="space-y-1 pt-1"><p class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Detected Column Mapping</p> <div class="border-border/60 bg-muted/20 divide-border/40 max-h-32 divide-y overflow-y-auto rounded-lg border p-2 text-[11px]"><!--[-->`);
                      const each_array_1 = ensure_array_like(analysis.column_analysis);
                      for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
                        let col = each_array_1[index];
                        $$renderer5.push(`<div class="flex items-center justify-between px-1 py-1"><span class="text-foreground max-w-[140px] truncate font-mono">${escape_html(col.raw)}</span> <span class="text-muted-foreground text-[10px]">→</span> `);
                        if (col.status === "aptitude") {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<span class="text-primary font-mono text-[10px] font-semibold">${escape_html(col.matched_to)}</span>`);
                        } else if (col.status === "required") {
                          $$renderer5.push("<!--[1-->");
                          $$renderer5.push(`<span class="font-mono text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">${escape_html(col.matched_to)}</span>`);
                        } else if (col.status === "optional" || col.status === "fuzzy") {
                          $$renderer5.push("<!--[2-->");
                          $$renderer5.push(`<span class="text-muted-foreground font-mono text-[10px]">${escape_html(col.matched_to)}</span>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                          $$renderer5.push(`<span class="text-muted-foreground/60 text-[10px]">ignored</span>`);
                        }
                        $$renderer5.push(`<!--]--></div>`);
                      }
                      $$renderer5.push(`<!--]--></div></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> <div class="space-y-4 lg:col-span-5">`);
                  if (isApplicants()) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="border-border/80 bg-muted/30 space-y-3 rounded-xl border p-4"><div class="text-foreground flex items-center gap-1.5 text-xs font-bold">`);
                    Users($$renderer5, { class: "text-primary size-3.5" });
                    $$renderer5.push(`<!----> <span>Expected Applicant Intake Columns</span></div> <p class="text-muted-foreground text-[11px] leading-relaxed">Headers are normalized automatically (e.g. "Birth
                            Date" or "dob" map to birthdate).</p> <div class="max-h-[380px] space-y-1.5 overflow-y-auto pr-1"><!--[-->`);
                    const each_array_2 = ensure_array_like(applicantGuideColumns);
                    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                      let col = each_array_2[$$index_2];
                      $$renderer5.push(`<div class="bg-background border-border/50 flex items-center justify-between rounded-lg border p-2 text-xs"><div><div class="flex items-center gap-1.5"><span class="text-foreground font-semibold">${escape_html(col.name)}</span> `);
                      if (col.floor) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<span class="bg-primary/10 py-0.2 text-primary rounded px-1 text-[9px] font-bold">FLOOR</span>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></div> <p class="text-muted-foreground font-mono text-[10px]">${escape_html(col.code)}</p></div> <span class="text-muted-foreground text-right text-[10px]">${escape_html(col.note)}</span></div>`);
                    }
                    $$renderer5.push(`<!--]--></div></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<div class="border-border/80 bg-muted/30 space-y-3 rounded-xl border p-4"><div class="text-foreground flex items-center gap-1.5 text-xs font-bold">`);
                    Brain_circuit($$renderer5, { class: "text-primary size-4" });
                    $$renderer5.push(`<!----> <span>Institutional Aptitude Areas</span></div> <p class="text-muted-foreground text-[11px] leading-relaxed">Examinee scores must correspond to the institution's
                            cognitive test battery. Raw scores are converted
                            through active conversion tables.</p> <div class="bg-background border-primary/30 ring-primary/20 rounded-lg border p-2.5 text-xs ring-1"><div class="flex items-center justify-between"><span class="text-foreground font-bold">Applicant Number</span> <span class="bg-primary/10 text-primary rounded px-1.5 py-0.5 text-[9px] font-bold">MANDATORY KEY</span></div> <p class="text-muted-foreground mt-0.5 text-[10px]">e.g. <span class="font-mono">APP-2026-1001</span> or reference code</p></div> <div class="max-h-[320px] space-y-1.5 overflow-y-auto pr-1"><!--[-->`);
                    const each_array_3 = ensure_array_like(activeAptitudeAreas());
                    for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                      let area = each_array_3[$$index_3];
                      $$renderer5.push(`<div class="bg-background border-border/60 hover:border-border flex items-center justify-between rounded-lg border p-2.5 text-xs transition-colors"><div class="min-w-0 pr-2"><div class="flex flex-wrap items-center gap-1.5"><span class="text-primary font-mono text-xs font-bold">${escape_html(area.code)}</span> <span class="text-foreground text-[11px] font-medium">· ${escape_html(area.name)}</span></div></div> `);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "shrink-0 font-mono text-[10px]",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Max: ${escape_html(area.max_items ?? 100)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div>`);
                    }
                    $$renderer5.push(`<!--]--></div> <div class="bg-muted/40 text-muted-foreground flex items-center gap-2 rounded-lg p-2 text-[11px]">`);
                    Info($$renderer5, { class: "text-primary size-3.5 shrink-0" });
                    $$renderer5.push(`<!----> <span>Unrecognized non-aptitude columns (e.g. notes)
                                are ignored safely.</span></div></div>`);
                  }
                  $$renderer5.push(`<!--]--></div></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      class: "border-border/60 flex shrink-0 items-center justify-between border-t pt-4 sm:justify-between",
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "ghost",
                          onclick: handleClose,
                          disabled: submitting,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          variant: "default",
                          onclick: handleSubmit,
                          disabled: !isReadyToImport() || submitting,
                          size: "lg",
                          class: "gap-2 px-6 font-bold",
                          children: ($$renderer7) => {
                            if (submitting) {
                              $$renderer7.push("<!--[0-->");
                              Loader_circle($$renderer7, { class: "size-4 animate-spin" });
                              $$renderer7.push(`<!----> <span>Saving rows for review…</span>`);
                            } else {
                              $$renderer7.push("<!--[-1-->");
                              Upload($$renderer7, { class: "size-4" });
                              $$renderer7.push(`<!----> <span>Review ${escape_html(analysis.total_rows)} rows</span>`);
                            }
                            $$renderer7.push(`<!--]-->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { open });
  });
}
export {
  ContextualImportModal as C
};
