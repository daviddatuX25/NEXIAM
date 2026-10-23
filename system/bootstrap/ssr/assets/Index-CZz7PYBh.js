import { g as sanitize_props, i as spread_props, j as slot, a as ensure_array_like, c as attr_class, A as clsx, b as attr, e as escape_html, B as Button, h as head, d as derived } from "../app.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { D as Dialog_trigger } from "./dialog-trigger-46d4JmXJ.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { D as Download } from "./download-n32NlL8c.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./book-open-B6eMguqY.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./x-CnxrMbMn.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./clock-B4fXoAi9.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function File_stack($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      { "d": "M11 21a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1" }
    ],
    [
      "path",
      { "d": "M16 16a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1" }
    ],
    [
      "path",
      {
        "d": "M21 6a2 2 0 0 0-.586-1.414l-2-2A2 2 0 0 0 17 2h-3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "file-stack" },
    $$sanitized_props,
    {
      /**
       * @component @name FileStack
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTEgMjFhMSAxIDAgMCAxLTEgMUg0YTEgMSAwIDAgMS0xLTF2LThhMSAxIDAgMCAxIDEtMSIgLz4KICA8cGF0aCBkPSJNMTYgMTZhMSAxIDAgMCAxLTEgMUg5YTEgMSAwIDAgMS0xLTFWOGExIDEgMCAwIDEgMS0xIiAvPgogIDxwYXRoIGQ9Ik0yMSA2YTIgMiAwIDAgMC0uNTg2LTEuNDE0bC0yLTJBMiAyIDAgMCAwIDE3IDJoLTNhMSAxIDAgMCAwLTEgMXY4YTEgMSAwIDAgMCAxIDFoNmExIDEgMCAwIDAgMS0xeiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/file-stack
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
function NewAnswerSheetDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { create_url = "" } = $$props;
    let open = false;
    const form = useForm({
      code: `ICAT-AS-${(/* @__PURE__ */ new Date()).getFullYear()}`,
      name: `ICAT Answersheet ${(/* @__PURE__ */ new Date()).getFullYear()}`,
      registration_mode: "document_bound",
      qr_mode: "qr_disabled",
      requested_page_count: 1
    });
    const registrationOptions = [
      {
        value: "document_bound",
        title: "Standard Paper / Document Boundary",
        description: "Detects the physical paper edges. Best for ordinary printing and photocopying.",
        badge: "Recommended"
      },
      {
        value: "fiducial_anchored",
        title: "High-Precision Corner Markers",
        description: "Prints four corner markers for tightly controlled alignment.",
        badge: "Pinned"
      }
    ];
    const qrOptions = [
      {
        value: "qr_disabled",
        title: "No production QR",
        description: "Clean sheets; staff selects pages during scanning."
      },
      {
        value: "qr_signed",
        title: "Signed production QR",
        description: "Prints a signed instance and page barcode."
      }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Dialog($$renderer3, {
        get open() {
          return open;
        },
        set open($$value) {
          open = $$value;
          $$settled = false;
        },
        children: ($$renderer4) => {
          {
            let child = function($$renderer5, { props }) {
              Button($$renderer5, spread_props([
                props,
                {
                  class: "min-h-11 gap-2",
                  children: ($$renderer6) => {
                    Plus($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!---->New Answer Sheet`);
                  },
                  $$slots: { default: true }
                }
              ]));
            };
            Dialog_trigger($$renderer4, { child, $$slots: { child: true } });
          }
          $$renderer4.push(`<!----> `);
          Dialog_content($$renderer4, {
            class: "max-h-[90vh] overflow-y-auto sm:max-w-2xl",
            children: ($$renderer5) => {
              Dialog_header($$renderer5, {
                children: ($$renderer6) => {
                  Dialog_title($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->New Answer Sheet`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Dialog_description($$renderer6, {
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Set the paper structure before entering Studio. These choices
                control layout, printing, and scanning.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> <form class="space-y-5"><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5">`);
              Label($$renderer5, {
                for: "new-sheet-code",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Reference code`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Input($$renderer5, {
                id: "new-sheet-code",
                placeholder: `e.g. ICAT-AS-${(/* @__PURE__ */ new Date()).getFullYear()}`,
                class: "font-mono text-xs uppercase",
                "aria-invalid": Boolean(form.errors.code),
                required: true,
                get value() {
                  return form.code;
                },
                set value($$value) {
                  form.code = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div> <div class="space-y-1.5">`);
              Label($$renderer5, {
                for: "new-sheet-name",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Answer Sheet name`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Input($$renderer5, {
                id: "new-sheet-name",
                placeholder: `e.g. ICAT Answersheet ${(/* @__PURE__ */ new Date()).getFullYear()}`,
                "aria-invalid": Boolean(form.errors.name),
                required: true,
                get value() {
                  return form.name;
                },
                set value($$value) {
                  form.name = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div></div> <fieldset class="space-y-2"><legend class="flex items-center gap-2 text-sm font-semibold">`);
              Scan_line($$renderer5, { class: "text-primary size-4" });
              $$renderer5.push(`<!----> Paper registration</legend> <div class="grid gap-2"><!--[-->`);
              const each_array = ensure_array_like(registrationOptions);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let option = each_array[$$index];
                $$renderer5.push(`<label${attr_class(clsx([
                  "cursor-pointer rounded-xl border p-3 transition-colors",
                  form.registration_mode === option.value ? "border-primary bg-primary/5 ring-primary/30 ring-1" : "border-border bg-card hover:bg-muted/40"
                ]))}><span class="flex items-start gap-3"><input type="radio" name="registration_mode"${attr("value", option.value)}${attr("checked", form.registration_mode === option.value, true)} class="accent-primary mt-1 size-4"/> <span class="min-w-0 flex-1"><span class="flex flex-wrap items-center justify-between gap-2"><span class="text-sm font-semibold">${escape_html(option.title)}</span> <span class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase">${escape_html(option.badge)}</span></span> <span class="text-muted-foreground mt-1 block text-xs leading-relaxed">${escape_html(option.description)}</span></span></span></label>`);
              }
              $$renderer5.push(`<!--]--></div></fieldset> <fieldset class="space-y-2"><legend class="flex items-center gap-2 text-sm font-semibold">`);
              Qr_code($$renderer5, { class: "text-primary size-4" });
              $$renderer5.push(`<!----> Barcode and page tracking</legend> <div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
              const each_array_1 = ensure_array_like(qrOptions);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let option = each_array_1[$$index_1];
                $$renderer5.push(`<label${attr_class(clsx([
                  "cursor-pointer rounded-xl border p-3 transition-colors",
                  form.qr_mode === option.value ? "border-primary bg-primary/5 ring-primary/30 ring-1" : "border-border bg-card hover:bg-muted/40"
                ]))}><span class="flex items-start gap-2"><input type="radio" name="qr_mode"${attr("value", option.value)}${attr("checked", form.qr_mode === option.value, true)} class="accent-primary mt-0.5 size-4"/> <span><span class="block text-sm font-semibold">${escape_html(option.title)}</span> <span class="text-muted-foreground mt-1 block text-xs leading-relaxed">${escape_html(option.description)}</span></span></span></label>`);
              }
              $$renderer5.push(`<!--]--></div></fieldset> <fieldset class="space-y-2"><legend class="flex items-center gap-2 text-sm font-semibold">`);
              File_stack($$renderer5, { class: "text-primary size-4" });
              $$renderer5.push(`<!----> Page count</legend> <div class="grid grid-cols-4 gap-2"><!--[-->`);
              const each_array_2 = ensure_array_like([1, 2, 3, 4]);
              for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                let pageCount = each_array_2[$$index_2];
                $$renderer5.push(`<label${attr_class(clsx([
                  "cursor-pointer rounded-lg border px-2 py-2.5 text-center text-xs font-semibold transition-colors",
                  form.requested_page_count === pageCount ? "border-primary bg-primary/10 text-primary ring-primary/30 ring-1" : "border-border bg-card text-muted-foreground hover:bg-muted/40 hover:text-foreground"
                ]))}><input class="sr-only" type="radio" name="requested_page_count"${attr("value", pageCount)}${attr("checked", form.requested_page_count === pageCount, true)}/> ${escape_html(pageCount)}
                            ${escape_html(pageCount === 1 ? "Page" : "Pages")}</label>`);
              }
              $$renderer5.push(`<!--]--></div> <p class="text-muted-foreground text-xs leading-relaxed">${escape_html(form.requested_page_count === 1 ? "Single-page sheet: design in Visual Designer, or upload a 1-page PDF, Word, or camera photo." : `Multi-page sheet (${form.requested_page_count} pages): design across all ${form.requested_page_count} pages in Visual Designer, or upload an existing ${form.requested_page_count}-page PDF / Word document.`)}</p></fieldset> `);
              if (Object.keys(form.errors).length) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div role="alert" class="border-destructive/40 bg-destructive/8 text-destructive flex items-start gap-2 rounded-lg border p-3 text-sm">`);
                Circle_alert($$renderer5, { class: "mt-0.5 size-4 shrink-0" });
                $$renderer5.push(`<!----> <span>${escape_html(Object.values(form.errors)[0])}</span></div>`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              Dialog_footer($$renderer5, {
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    type: "submit",
                    disabled: form.processing,
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->${escape_html(form.processing ? "Creating Answer Sheet…" : "Create and open Studio")}`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></form>`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!---->`);
        },
        $$slots: { default: true }
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { drafts = [], active_template = null, create_url = "" } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials", href: "/setup/exam-materials" },
      { label: "Answer Sheet Studio" }
    ];
    function formatStage(stage) {
      return (stage || "draft").replaceAll("_", " ");
    }
    function stageBadgeVariant(stage) {
      if (stage === "compiled" || stage === "physical_qualified") return "default";
      if (stage === "digital_verified" || stage === "mapping_complete") return "secondary";
      return "outline";
    }
    function pageLabel(count) {
      if (!count) return "No saved preview";
      return `${count} ${count === 1 ? "page" : "pages"}`;
    }
    const activeDraft = derived(() => drafts.find((d) => d.is_active_version) || null);
    const authoringDrafts = derived(() => drafts.filter((d) => !d.is_active_version && d.compiled_version_status !== "retired"));
    const retiredDrafts = derived(() => drafts.filter((d) => d.compiled_version_status === "retired"));
    head("bux84p", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Answer Sheet Studio · Exam materials</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            $$renderer4.push(`<div class="flex items-center gap-2"><button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex min-h-10 items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold shadow-xs transition">`);
            Upload($$renderer4, { class: "size-4 text-muted-foreground" });
            $$renderer4.push(`<!----> <span>Import Bundle (.zip)</span></button> `);
            NewAnswerSheetDialog($$renderer4, { create_url });
            $$renderer4.push(`<!----></div>`);
          };
          PageHeaderBar($$renderer3, {
            title: "Answer Sheet Studio",
            description: "Design and maintain the optical answer sheets used by exam Question Booklets.",
            actions
          });
        }
        $$renderer3.push(`<!----> <section class="space-y-3"><div class="flex items-center justify-between"><h2 class="text-muted-foreground flex items-center gap-2 text-xs font-bold tracking-wider uppercase"><span${attr_class(`size-2.5 rounded-full bg-emerald-500 ${activeDraft() ? "animate-pulse" : "opacity-50"}`)}></span> Active Answer Sheet (In Use by Booklets)</h2> `);
        if (activeDraft()) {
          $$renderer3.push("<!--[0-->");
          Badge($$renderer3, {
            class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Live Version ${escape_html(activeDraft().compiled_version_number ?? 1)}`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (activeDraft()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<article class="border-emerald-500/30 bg-emerald-500/5 grid gap-4 rounded-xl border p-4 shadow-xs transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
          MaterialDocumentPreview($$renderer3, {
            src: activeDraft().preview_url ?? active_template?.preview_url,
            alt: `${activeDraft().name} active answer sheet preview`,
            kind: "answer_sheet",
            title: activeDraft().name,
            page_count: activeDraft().preview_page_count
          });
          $$renderer3.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-start gap-2"><h3 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(activeDraft().name)}</h3> `);
          Badge($$renderer3, {
            class: "bg-emerald-600 text-white hover:bg-emerald-600",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Active · In Production`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> <p class="text-foreground/80 mt-1 text-sm font-medium">${escape_html(activeDraft().authoring_method)} · Version ${escape_html(activeDraft().compiled_version_number ?? 1)}</p> <p class="text-muted-foreground mt-1 text-sm">${escape_html(pageLabel(activeDraft().preview_page_count))}</p> <p class="text-muted-foreground mt-3 text-xs">Updated ${escape_html(new Date(activeDraft().updated_at).toLocaleDateString())}</p></div> <div class="flex items-center gap-2 sm:self-center"><a${attr("href", `/setup/exam-materials/answer-sheets/${activeDraft().id}/export`)} download="" class="border-border bg-card hover:bg-muted text-foreground inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition" title="Export .zip bundle"${attr("aria-label", `Export ${activeDraft().name} .zip bundle`)}>`);
          Download($$renderer3, { class: "text-muted-foreground size-4" });
          $$renderer3.push(`<!----> <span class="hidden md:inline">Export</span></a> `);
          Link($$renderer3, {
            href: activeDraft().url,
            viewTransition: true,
            children: ($$renderer4) => {
              Button($$renderer4, {
                variant: "outline",
                class: "min-h-11 w-full border-emerald-500/40 text-emerald-700 hover:bg-emerald-50 sm:w-auto dark:text-emerald-300 dark:hover:bg-emerald-950/30",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Inspect Active Version`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></article>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border bg-card/50 flex flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center"><p class="text-foreground text-sm font-medium">No active answer sheet version yet.</p> <p class="text-muted-foreground mt-1 max-w-sm text-xs leading-relaxed">Activate a compiled draft below to make it the official
                        answer sheet used by Question Booklets.</p></div>`);
        }
        $$renderer3.push(`<!--]--></section> <section class="space-y-3"><div class="flex items-center justify-between"><h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Authoring Workspaces &amp; Candidates (${escape_html(authoringDrafts().length)})</h2></div> `);
        if (authoringDrafts().length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center"><div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl">`);
          Layers($$renderer3, { class: "text-muted-foreground size-6" });
          $$renderer3.push(`<!----></div> <h3 class="mt-4 font-semibold">No Drafts in Progress</h3> <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">Create an Answer Sheet to design with the Sheet Designer
                        or map an uploaded document.</p> <div class="mt-5 flex justify-center">`);
          NewAnswerSheetDialog($$renderer3, { create_url });
          $$renderer3.push(`<!----></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"><!--[-->`);
          const each_array = ensure_array_like(authoringDrafts());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let draft = each_array[$$index];
            $$renderer3.push(`<article class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
            MaterialDocumentPreview($$renderer3, {
              src: draft.preview_url,
              alt: `${draft.name} saved preview`,
              kind: "answer_sheet",
              title: draft.name,
              page_count: draft.preview_page_count
            });
            $$renderer3.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-start gap-2"><h3 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(draft.name)}</h3> `);
            Badge($$renderer3, {
              variant: stageBadgeVariant(draft.stage),
              class: "capitalize",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(formatStage(draft.stage))}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            if (draft.can_activate) {
              $$renderer3.push("<!--[0-->");
              Badge($$renderer3, {
                class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Ready to Activate`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (!draft.can_edit && !draft.can_activate) {
              $$renderer3.push("<!--[0-->");
              Badge($$renderer3, {
                variant: "destructive",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Read-only`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <p class="text-foreground/80 mt-1 text-sm font-medium">${escape_html(draft.authoring_method)} `);
            if (draft.compiled_version_number) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`· Version ${escape_html(draft.compiled_version_number)}`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></p> <p class="text-muted-foreground mt-1 text-sm">${escape_html(pageLabel(draft.preview_page_count))}</p> <p class="text-muted-foreground mt-3 text-xs">Updated ${escape_html(new Date(draft.updated_at).toLocaleDateString())}</p></div> <div class="flex flex-wrap items-center gap-2 sm:self-center"><a${attr("href", `/setup/exam-materials/answer-sheets/${draft.id}/export`)} download="" class="border-border bg-card hover:bg-muted text-foreground inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition" title="Export .zip bundle"${attr("aria-label", `Export ${draft.name} .zip bundle`)}>`);
            Download($$renderer3, { class: "text-muted-foreground size-4" });
            $$renderer3.push(`<!----> <span class="hidden md:inline">Export</span></a> `);
            if (draft.can_activate) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="focus-visible:ring-ring inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-500 focus-visible:ring-2 focus-visible:outline-none" title="Activate for Exam Booklets"${attr("aria-label", `Activate ${draft.name} for Exam Booklets`)}>`);
              Sparkles($$renderer3, { class: "size-4" });
              $$renderer3.push(`<!----> <span>Activate for Booklets</span></button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (draft.can_discard) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="border-rose-500/30 bg-rose-500/10 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/30 inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition" title="Discard answer sheet draft"${attr("aria-label", `Discard ${draft.name} draft`)}>`);
              Trash_2($$renderer3, { class: "size-4" });
              $$renderer3.push(`<!----> <span class="hidden md:inline">Discard</span></button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            Link($$renderer3, {
              href: draft.url,
              viewTransition: true,
              children: ($$renderer4) => {
                Button($$renderer4, {
                  variant: "outline",
                  class: "min-h-11 w-full sm:w-auto",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(draft.can_edit ? "Continue editing" : "Open Studio")}`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div></article>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--></section> `);
        if (retiredDrafts().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<details class="border-border bg-card group rounded-xl border p-4 transition"><summary class="text-muted-foreground flex cursor-pointer list-none items-center justify-between text-xs font-bold tracking-wider uppercase select-none"><span>Archived &amp; Past Versions (${escape_html(retiredDrafts().length)})</span> `);
          Chevron_down($$renderer3, { class: "size-4 transition-transform group-open:rotate-180" });
          $$renderer3.push(`<!----></summary> <div class="divide-border mt-4 divide-y border-t pt-2"><!--[-->`);
          const each_array_1 = ensure_array_like(retiredDrafts());
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let draft = each_array_1[$$index_1];
            $$renderer3.push(`<div class="flex items-center justify-between py-3 text-xs"><div><span class="text-foreground font-semibold">${escape_html(draft.name)}</span> <span class="text-muted-foreground ml-2">Version ${escape_html(draft.compiled_version_number ?? 1)} · Retired</span></div> <div class="flex items-center gap-2"><a${attr("href", `/setup/exam-materials/answer-sheets/${draft.id}/export`)} download="" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-md px-2 py-1 transition">`);
            Download($$renderer3, { class: "size-3.5" });
            $$renderer3.push(`<!----> <span>Export</span></a> `);
            Link($$renderer3, {
              href: draft.url,
              class: "text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-md px-2 py-1 transition",
              children: ($$renderer4) => {
                $$renderer4.push(`<span>View</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div></div>`);
          }
          $$renderer3.push(`<!--]--></div></details>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Index as default
};
