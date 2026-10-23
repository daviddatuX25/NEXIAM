import { e as escape_html, B as Button, a as ensure_array_like, b as attr, d as derived, v as error, q as success } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { L as Link_2 } from "./link-2-Bpg4gDHg.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./chevron-left-DCSOZwZl.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      templates = [],
      booklets = [],
      pairs = [],
      print_jobs = [],
      readiness = {
        active_pair: false,
        draft_templates: false,
        draft_booklets: false,
        paper: "long"
      },
      errors = {}
    } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "OMR Configuration" }
    ];
    let confirmKey = null;
    const draftTemplatesCount = derived(() => templates.reduce((acc, t) => acc + (t.versions?.filter((v) => v.status === "draft").length || 0), 0));
    const draftBookletsCount = derived(() => booklets.reduce((acc, b) => acc + (b.versions?.filter((v) => v.status === "draft").length || 0), 0));
    const activePairs = derived(() => pairs.filter((p) => p.status === "active"));
    function formatDate(iso) {
      if (!iso) return "—";
      const d = new Date(iso);
      return Number.isNaN(d.getTime()) ? iso : d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    }
    function retire(kind, id) {
      router.post("/setup/omr/retire", { kind, id }, {
        preserveScroll: true,
        onSuccess: () => {
          confirmKey = null;
          success("Configuration retired.");
        },
        onError: (e) => {
          confirmKey = null;
          error(e.omr_retire ?? "Could not retire.");
        }
      });
    }
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        {
          let actions = function($$renderer4) {
            Button($$renderer4, {
              href: "/setup/exam-materials/answer-sheets",
              variant: "outline",
              size: "sm",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Answer Sheet Studio`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              href: "/setup/exam-materials",
              variant: "outline",
              size: "sm",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Exam materials`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
          };
          PageHeaderBar($$renderer3, {
            title: "OMR Configuration",
            description: "Answer-sheet designs, answer keys, and exam scanning pairings.",
            actions
          });
        }
        $$renderer3.push(`<!----> <section class="space-y-8 p-6">`);
        if (errors.omr_retire) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-destructive/50 bg-destructive/10 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3 text-sm">`);
          Circle_alert($$renderer3, { class: "size-4 shrink-0" });
          $$renderer3.push(`<!----> <span>${escape_html(errors.omr_retire)}</span></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="grid gap-5 md:grid-cols-2"><div class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"><div><div class="flex items-start justify-between"><div class="flex size-11 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">`);
        File_text($$renderer3, { class: "size-6" });
        $$renderer3.push(`<!----></div> `);
        Badge($$renderer3, {
          variant: "secondary",
          class: "text-xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(templates.length)}
                            ${escape_html(templates.length === 1 ? "template" : "templates")} `);
            if (draftTemplatesCount() > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-muted-foreground ml-1">· ${escape_html(draftTemplatesCount())} draft</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <h3 class="text-foreground mt-4 text-lg font-semibold">Answer-sheet templates</h3> <p class="text-muted-foreground mt-1 text-sm">Bubble-sheet designs with version history, printable
                        text, and PDF previews.</p></div> <div class="border-border/60 mt-5 flex flex-wrap items-center justify-between gap-2 border-t pt-4">`);
        Button($$renderer3, {
          href: "/setup/exam-materials/answer-sheets",
          variant: "outline",
          size: "xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open Answer Sheet Studio`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          href: "/setup/omr/templates",
          variant: "ghost",
          size: "xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->View templates (${escape_html(templates.length)}) `);
            Arrow_right($$renderer4, { class: "ml-1 size-3" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div> <div class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"><div><div class="flex items-start justify-between"><div class="flex size-11 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">`);
        Book_open($$renderer3, { class: "size-6" });
        $$renderer3.push(`<!----></div> `);
        Badge($$renderer3, {
          variant: "secondary",
          class: "text-xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(booklets.length)}
                            ${escape_html(booklets.length === 1 ? "booklet" : "booklets")} `);
            if (draftBookletsCount() > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-muted-foreground ml-1">· ${escape_html(draftBookletsCount())} draft</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <h3 class="text-foreground mt-4 text-lg font-semibold">Question booklets</h3> <p class="text-muted-foreground mt-1 text-sm">Answer keys with correct options (A–D) grouped by
                        aptitude area.</p></div> <div class="border-border/60 mt-5 flex flex-wrap items-center justify-between gap-2 border-t pt-4">`);
        Button($$renderer3, {
          href: "/setup/exam-materials/booklets",
          variant: "outline",
          size: "xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open Booklets`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div></div> <div class="border-border/90 bg-card rounded-xl border p-6"><div class="flex flex-wrap items-center justify-between gap-3"><div><div class="flex items-center gap-2"><h2 class="text-foreground text-base font-semibold">Active scanning pairs</h2> `);
        Badge($$renderer3, {
          variant: "secondary",
          class: "font-mono text-xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(activePairs().length)} active`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="text-muted-foreground mt-0.5 text-xs">What gets scanned and scored together. Each pair couples
                        one template version with an answer booklet version.</p></div> `);
        Button($$renderer3, {
          href: "/setup/omr/pairs",
          size: "sm",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Pair sheet &amp; booklet`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <div class="mt-6 space-y-4">`);
        if (activePairs().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<!--[-->`);
          const each_array = ensure_array_like(activePairs());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let pair = each_array[$$index];
            $$renderer3.push(`<div class="border-border/70 bg-muted/20 rounded-xl border p-4"><div class="flex flex-col gap-3.5 md:flex-row md:items-center md:justify-between"><div class="border-border/60 bg-card flex min-w-0 flex-1 items-center gap-3 rounded-lg border p-3.5"><div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">`);
            File_text($$renderer3, { class: "size-4.5" });
            $$renderer3.push(`<!----></div> <div class="min-w-0 flex-1"><div class="flex items-center gap-1.5"><strong class="text-foreground truncate text-sm font-semibold">${escape_html(pair.template_name)}</strong> `);
            Badge($$renderer3, {
              variant: "secondary",
              class: "shrink-0 text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->v${escape_html(pair.template_version)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <div class="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs"><span class="font-mono">${escape_html(pair.template_code)}</span> <span>·</span> <span>${escape_html(pair.page_width)}×${escape_html(pair.page_height)}</span></div></div> `);
            if (pair.template_version_id) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                href: `/setup/omr/templates/${pair.template_version_id}/preview`,
                variant: "ghost",
                size: "icon-xs",
                class: "text-muted-foreground shrink-0",
                title: "Preview sheet PDF",
                target: "_blank",
                rel: "noopener",
                children: ($$renderer4) => {
                  Eye($$renderer4, { class: "size-3.5" });
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <div class="flex shrink-0 items-center justify-center"><div class="border-primary/30 bg-primary/10 text-primary flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold">`);
            Link_2($$renderer3, { class: "size-3.5" });
            $$renderer3.push(`<!----> <span>Paired</span></div></div> <div class="border-border/60 bg-card flex min-w-0 flex-1 items-center gap-3 rounded-lg border p-3.5"><div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">`);
            Book_open($$renderer3, { class: "size-4.5" });
            $$renderer3.push(`<!----></div> <div class="min-w-0 flex-1"><div class="flex items-center gap-1.5"><strong class="text-foreground truncate text-sm font-semibold">${escape_html(pair.booklet_name)}</strong> `);
            Badge($$renderer3, {
              variant: "secondary",
              class: "shrink-0 text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->v${escape_html(pair.booklet_version)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <div class="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs"><span class="font-mono">${escape_html(pair.booklet_code)}</span> <span>·</span> <span>Answer key active</span></div></div> `);
            if (pair.booklet_id) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                href: `/setup/exam-materials/booklets/${pair.booklet_id}`,
                variant: "outline",
                size: "xs",
                class: "shrink-0",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->View key`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <div class="flex shrink-0 items-center justify-end">`);
            Button($$renderer3, {
              variant: "ghost",
              size: "sm",
              class: "text-muted-foreground hover:text-destructive",
              onclick: () => confirmKey = `pair-${pair.id}`,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Unpair`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div></div> `);
            if (confirmKey === `pair-${pair.id}`) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-xs"><span>Retire this active pair? Open grading
                                        sessions keep their pinned pair; new
                                        sessions cannot select it.</span> <div class="flex items-center gap-2">`);
              Button($$renderer3, {
                variant: "destructive",
                size: "xs",
                onclick: () => retire("pair", pair.id),
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Confirm retire`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----> `);
              Button($$renderer3, {
                variant: "outline",
                size: "xs",
                onclick: () => confirmKey = null,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></div></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border/80 rounded-xl border border-dashed p-8 text-center">`);
          Link_2($$renderer3, { class: "text-muted-foreground mx-auto size-8" });
          $$renderer3.push(`<!----> <p class="text-foreground mt-2 text-sm font-medium">No active scanning pairs</p> <p class="text-muted-foreground mt-1 text-xs">Pair an answer-sheet template with a question
                            booklet to enable OMR scoring.</p> `);
          Button($$renderer3, {
            href: "/setup/omr/pairs",
            size: "sm",
            class: "mt-4",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Activate a pair now`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        }
        $$renderer3.push(`<!--]--></div> `);
        if (draftTemplatesCount() > 0 || draftBookletsCount() > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="text-muted-foreground mt-5 rounded-lg border border-dashed border-amber-500/40 bg-amber-500/5 p-3.5 text-xs"><div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2">`);
          Sparkles($$renderer3, { class: "size-4 text-amber-600 dark:text-amber-400" });
          $$renderer3.push(`<!----> <span><strong>Unpaired drafts available:</strong> `);
          if (draftTemplatesCount() > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`${escape_html(draftTemplatesCount())}
                                    draft template(s)`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (draftTemplatesCount() > 0 && draftBookletsCount() > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`and`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (draftBookletsCount() > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`${escape_html(draftBookletsCount())} draft
                                    booklet(s)`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->.</span></div> `);
          Button($$renderer3, {
            href: "/setup/omr/pairs",
            variant: "outline",
            size: "xs",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Pair drafts now`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="border-border/70 bg-card rounded-xl border p-4"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-3"><div class="bg-muted text-muted-foreground flex size-9 items-center justify-center rounded-lg">`);
        Printer($$renderer3, { class: "size-4.5" });
        $$renderer3.push(`<!----></div> <div><div class="flex items-center gap-2"><h3 class="text-foreground text-sm font-semibold">Blank-sheet printing</h3> `);
        if (print_jobs.length > 0) {
          $$renderer3.push("<!--[0-->");
          Badge($$renderer3, {
            variant: "secondary",
            class: "text-[10px]",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(print_jobs.length)} recent`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground text-xs">Rendered on configured paper (${escape_html(readiness.paper)})
                            with millimeter precision.</p></div></div> `);
        if (print_jobs.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap items-center gap-2"><!--[-->`);
          const each_array_1 = ensure_array_like(print_jobs.slice(0, 3));
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let job = each_array_1[$$index_1];
            $$renderer3.push(`<div class="border-border/60 bg-muted/40 flex items-center gap-2 rounded-md border px-2.5 py-1 text-xs"><span class="text-muted-foreground font-mono text-[11px]">#${escape_html(job.id)}</span> `);
            Badge($$renderer3, {
              variant: job.status === "ready" ? "default" : "secondary",
              class: "text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(job.status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> <span class="text-muted-foreground text-[11px]">${escape_html(formatDate(job.created_at))}</span> `);
            if (job.status === "ready") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<a${attr("href", `/printing/jobs/${job.id}/download`)} class="text-primary font-medium hover:underline">PDF</a>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div></section>`);
      }
    });
  });
}
export {
  Index as default
};
