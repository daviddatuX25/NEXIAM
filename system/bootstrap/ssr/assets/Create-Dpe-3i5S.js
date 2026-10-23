import { d as derived, h as head, e as escape_html, B as Button, c as attr_class, b as attr, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { G as Globe } from "./globe-BeSTb5EF.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { B as Bot } from "./bot-LOOyhTeB.js";
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
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      audiences = [],
      source_types = [],
      current_office = null,
      message = null
    } = $$props;
    const urlParams = typeof window !== "undefined" ? new URLSearchParams(window.location.search) : null;
    const initialType = urlParams?.get("type") === "faq" ? "public_faq" : "institution_policy";
    const form = useForm({
      source_class: initialType,
      audience: initialType === "public_faq" ? "staff_and_applicant_inquiry" : "staff_only",
      title: "",
      question: "",
      answer: "",
      body_text: ""
    });
    const page = usePage();
    const flashStatus = derived(() => page.props.flash?.status ?? null);
    const flashError = derived(() => page.props.flash?.error ?? null);
    const isFaq = derived(() => form.source_class === "public_faq");
    const owningOffice = derived(() => officeLabel(current_office));
    const canSubmit = derived(() => Boolean(current_office) && form.title.trim() !== "");
    function officeLabel(office) {
      if (!office) return "No owning office assigned";
      if (office === "registrar") return "Registrar";
      if (office === "guidance") return "Guidance";
      return String(office);
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "FAQs & Internal Procedures", href: "/knowledge" },
      { label: isFaq() ? "New FAQ" : "New Internal Procedure" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("4ovnhq", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(isFaq() ? "New FAQ" : "New Internal Procedure")} · Xiam Knowledge &amp; Rules ·
        NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8"><div class="border-border/70 flex items-center gap-3 border-b pb-4">`);
          Link($$renderer4, {
            href: "/knowledge",
            children: ($$renderer5) => {
              Button($$renderer5, {
                variant: "outline",
                size: "sm",
                class: "h-9 gap-1.5 text-xs font-medium",
                children: ($$renderer6) => {
                  Arrow_left($$renderer6, { class: "size-3.5" });
                  $$renderer6.push(`<!----> FAQs &amp; Internal Procedures`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <div class="bg-border/80 h-4 w-px"></div> <span class="text-muted-foreground text-xs">Draft authoring workspace</span></div> `);
          PageHeaderBar($$renderer4, {
            title: isFaq() ? "Author Official FAQ" : "Author Internal Procedure",
            description: isFaq() ? "Draft an explanatory question and verified answer for review. Intended audience does not enable applicant AI retrieval." : "Draft a genuine internal staff procedure. Publish canonical public policies through Communications → Public policies."
          });
          $$renderer4.push(`<!----> `);
          if (flashStatus()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="status" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">${escape_html(flashStatus())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (flashError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="alert" class="border-destructive/40 bg-destructive/10 text-destructive rounded-xl border px-4 py-3 text-sm">${escape_html(flashError())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-8 text-center">`);
            Lock($$renderer4, { class: "text-destructive/80 mx-auto size-9" });
            $$renderer4.push(`<!----> <h3 class="text-foreground text-base font-semibold">Creation Denied</h3> <p class="text-muted-foreground mx-auto max-w-md text-sm">You do not have permission to author knowledge sources.
                    Contact your office administrator for access.</p></div>`);
          } else if (["failed", "error", "conflict"].includes(page_state)) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/40 bg-destructive/5 text-destructive rounded-xl border px-4 py-3 text-sm">${escape_html(message ?? "The draft authoring workspace is unavailable.")}</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12"><form class="border-border/80 bg-card space-y-6 rounded-2xl border p-5 shadow-xs sm:p-6 lg:col-span-7">`);
            if (Object.keys(form.errors ?? {}).length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div role="alert" class="border-destructive/40 bg-destructive/10 text-destructive flex items-center gap-2 rounded-xl border px-4 py-3 text-xs sm:text-sm">`);
              Circle_alert($$renderer4, { class: "size-4 shrink-0" });
              $$renderer4.push(`<!----> <span>Please review the highlighted validation errors
                                below.</span></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (!current_office) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div role="alert" class="flex items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-800 sm:text-sm dark:text-amber-200">`);
              Circle_alert($$renderer4, { class: "size-4 shrink-0" });
              $$renderer4.push(`<!----> <span>No owning office is assigned. Draft submission
                                is blocked until your office context is
                                resolved.</span></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="space-y-2">`);
            Label($$renderer4, {
              class: "text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Source Classification`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><button type="button"${attr_class(`flex flex-col items-start gap-1.5 rounded-xl border p-4 text-left transition-all ${!isFaq() ? "border-sky-500 bg-sky-500/5 shadow-xs ring-1 ring-sky-500/30" : "border-border bg-card hover:bg-muted/40"}`)}><div class="flex w-full items-center justify-between"><div class="text-foreground flex items-center gap-2 text-sm font-semibold">`);
            File_text($$renderer4, { class: "size-4 text-sky-600 dark:text-sky-400" });
            $$renderer4.push(`<!----> <span>Internal Procedure</span></div> `);
            if (!isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="rounded bg-sky-500/20 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 uppercase dark:text-sky-300">Selected</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground text-xs">Internal staff instructions, not public
                                    policies, cutoffs, or quotas.</p></button> <button type="button"${attr_class(`flex flex-col items-start gap-1.5 rounded-xl border p-4 text-left transition-all ${isFaq() ? "border-purple-500 bg-purple-500/5 shadow-xs ring-1 ring-purple-500/30" : "border-border bg-card hover:bg-muted/40"}`)}><div class="flex w-full items-center justify-between"><div class="text-foreground flex items-center gap-2 text-sm font-semibold">`);
            Circle_question_mark($$renderer4, { class: "size-4 text-purple-600 dark:text-purple-400" });
            $$renderer4.push(`<!----> <span>Official FAQ</span></div> `);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 uppercase dark:text-purple-300">Selected</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground text-xs">Reviewed explanatory questions and answers;
                                    applicant retrieval is separately gated.</p></button></div></div> <div class="space-y-1.5">`);
            Label($$renderer4, {
              for: "title",
              class: "text-xs font-semibold",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(isFaq() ? "FAQ Topic / Working Title" : "Procedure Title")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "title",
              placeholder: isFaq() ? "e.g. Document Submission FAQ" : "e.g. Registrar Document Verification Procedure",
              required: true,
              class: "min-h-10 text-sm",
              get value() {
                return form.title;
              },
              set value($$value) {
                form.title = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (form.errors?.title) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive mt-1 text-xs">${escape_html(form.errors.title)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
            Label($$renderer4, {
              class: "text-xs font-semibold",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Intended Audience (not an AI access grant)`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="grid grid-cols-1 gap-2.5"><button type="button"${attr("aria-pressed", form.audience === "staff_and_applicant_inquiry")}${attr_class(`flex flex-col justify-center rounded-xl border p-3 text-left transition-all ${form.audience === "staff_and_applicant_inquiry" ? "border-primary bg-primary/10 text-primary font-medium shadow-xs" : "border-border bg-card text-muted-foreground hover:bg-muted/40"}`)}><div class="text-foreground flex items-center gap-1.5 text-xs font-semibold">`);
              Globe($$renderer4, { class: "size-3.5 text-emerald-600 dark:text-emerald-400" });
              $$renderer4.push(`<!----> <span>Staff and applicant inquiry</span></div> <span class="text-muted-foreground mt-0.5 text-[11px]">For reviewed explanatory FAQ content</span></button></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="grid grid-cols-1 gap-2.5"><button type="button"${attr("aria-pressed", form.audience === "staff_only")}${attr_class(`flex flex-col justify-center rounded-xl border p-3 text-left transition-all ${form.audience === "staff_only" ? "border-primary bg-primary/10 text-primary font-medium shadow-xs" : "border-border bg-card text-muted-foreground hover:bg-muted/40"}`)}><div class="text-foreground flex items-center gap-1.5 text-xs font-semibold">`);
              Lock($$renderer4, { class: "text-muted-foreground size-3.5" });
              $$renderer4.push(`<!----> <span>Internal to office</span></div> <span class="text-muted-foreground mt-0.5 text-[11px]">${escape_html(owningOffice())} staff only</span></button></div> <p class="text-muted-foreground text-xs leading-relaxed">Internal procedures are visible to authorized
                                staff only. For public institutional policies,
                                use Communications → Public policies.</p>`);
            }
            $$renderer4.push(`<!--]--></div> `);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-1.5">`);
              Label($$renderer4, {
                for: "question",
                class: "text-xs font-semibold",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Full Applicant Question`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Textarea($$renderer4, {
                id: "question",
                placeholder: "e.g. When is the deadline for Senior High Form 137 submission?",
                rows: "3",
                required: true,
                class: "min-h-[85px] w-full text-sm leading-relaxed",
                get value() {
                  return form.question;
                },
                set value($$value) {
                  form.question = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              if (form.errors?.question) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive text-xs">${escape_html(form.errors.question)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
              Label($$renderer4, {
                for: "answer",
                class: "text-xs font-semibold",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Official Answer Text`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Textarea($$renderer4, {
                id: "answer",
                placeholder: "e.g. Form 137 must be submitted to the Registrar window no later than 5:00 PM on Friday, October 15. Late documents require an approved extension request.",
                rows: "6",
                required: true,
                class: "min-h-[140px] w-full text-sm leading-relaxed",
                get value() {
                  return form.answer;
                },
                set value($$value) {
                  form.answer = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              if (form.errors?.answer) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive text-xs">${escape_html(form.errors.answer)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="space-y-1.5">`);
              Label($$renderer4, {
                for: "body_text",
                class: "text-xs font-semibold",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Internal Procedure Content`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Textarea($$renderer4, {
                id: "body_text",
                placeholder: "Enter internal procedure or official FAQ text. Do not include individual applicant personal data.",
                rows: "10",
                required: true,
                class: "min-h-[200px] w-full font-mono text-xs leading-relaxed sm:text-sm",
                get value() {
                  return form.body_text;
                },
                set value($$value) {
                  form.body_text = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              if (form.errors?.body_text) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive text-xs">${escape_html(form.errors.body_text)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            }
            $$renderer4.push(`<!--]--> <div class="border-border/60 flex items-center gap-3 border-t pt-3">`);
            Button($$renderer4, {
              type: "submit",
              class: "min-h-10 gap-2 px-5 font-medium shadow-xs",
              disabled: form.processing || !canSubmit(),
              children: ($$renderer5) => {
                Plus($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> ${escape_html(form.processing ? "Saving draft…" : isFaq() ? "Save FAQ draft" : "Save procedure draft")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Link($$renderer4, {
              href: "/knowledge",
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
            $$renderer4.push(`<!----></div></form> <div class="space-y-5 lg:col-span-5"><div class="border-border/80 bg-card space-y-4 rounded-2xl border p-5 shadow-xs"><div class="flex items-center justify-between"><div class="flex items-center gap-2">`);
            Eye($$renderer4, { class: "text-primary size-4" });
            $$renderer4.push(`<!----> <span class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Live Citation Preview</span></div> <span class="bg-muted text-muted-foreground rounded px-2 py-0.5 font-mono text-[10px]">Draft v1</span></div> <div class="border-border/60 bg-muted/20 space-y-3 rounded-xl border p-4"><div class="flex items-center justify-between gap-2"><span class="bg-background border-border inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-semibold uppercase">`);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`FAQ`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`Policy`);
            }
            $$renderer4.push(`<!--]--></span> <span class="text-muted-foreground text-[11px] font-medium">${escape_html(owningOffice())}</span></div> <div class="space-y-1"><h4 class="text-foreground text-sm font-bold">`);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`${escape_html(form.question || form.title || "Untitled question...")}`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`${escape_html(form.title || "Untitled policy...")}`);
            }
            $$renderer4.push(`<!--]--></h4> <div class="text-muted-foreground text-[10px]">Scope: <span class="text-foreground font-medium">${escape_html(form.audience === "staff_only" ? "Office Internal" : "Public & Applicants")}</span></div></div> <div class="border-border/50 text-muted-foreground border-t pt-2 text-xs leading-relaxed">`);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-foreground/80 mb-1 text-[10px] font-semibold uppercase">Xiam Answer Citation</p> <p class="italic">${escape_html(form.answer || "Enter an official answer on the left to see the citation preview...")}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="text-foreground/80 mb-1 text-[10px] font-semibold uppercase">Policy Scope Excerpt</p> <p class="italic">${escape_html(form.body_text || "Enter policy content on the left to preview canonical extraction text...")}</p>`);
            }
            $$renderer4.push(`<!--]--></div></div> <div class="bg-primary/5 border-primary/20 text-muted-foreground space-y-1.5 rounded-xl border p-3 text-xs"><div class="text-primary flex items-center gap-1.5 text-[11px] font-semibold">`);
            Bot($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> <span>Assistant Grounding Pipeline</span></div> <p class="text-[11px] leading-relaxed">Saving creates an unapproved Draft version. The
                                background queue extracts canonical SHA-256
                                digests and seals review fixtures. An
                                independent supervisor must verify and sign off
                                before this source can be cited.</p></div></div></div></div>`);
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
  Create as default
};
