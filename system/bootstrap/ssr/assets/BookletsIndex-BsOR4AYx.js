import { h as head, e as escape_html, b as attr, c as attr_class, a as ensure_array_like, B as Button, d as derived } from "../app.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { N as NewBookletDialog } from "./NewBookletDialog-CRKPogkR.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./Icon-Dal7PqKK.js";
import "./x-CnxrMbMn.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "./dialog-trigger-46d4JmXJ.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./input-BUZAZKIK.js";
import "./label-dGY6L9vm.js";
import "./circle-check-C59ZS7Zv.js";
import "./plus-DjyPLC7A.js";
import "./noop-BQS7OFs8.js";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function BookletsIndex($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      answer_sheets = [],
      booklets = [],
      errors = {},
      can_view_question_content = false,
      enable_multi_set_booklets = false,
      exam_feature_policy_version = 1
    } = $$props;
    let setPolicySaving = false;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials", href: "/setup/exam-materials" },
      { label: "Booklets" }
    ];
    let selectedBooklet = null;
    let discardDialogOpen = false;
    let archiveDialogOpen = false;
    const draftBooklets = derived(() => booklets.filter((b) => b.lifecycle_status === "draft"));
    const officialBooklets = derived(() => booklets.filter((b) => b.lifecycle_status === "official" || !b.lifecycle_status && b.status === "finalized"));
    const archivedBooklets = derived(() => booklets.filter((b) => b.lifecycle_status === "archived"));
    function confirmDiscard() {
      if (!selectedBooklet?.destroy_url) return;
      router.delete(selectedBooklet.destroy_url, {
        preserveScroll: true,
        onSuccess: () => {
          discardDialogOpen = false;
          selectedBooklet = null;
        }
      });
    }
    function confirmArchive() {
      if (!selectedBooklet?.archive_url) return;
      router.post(selectedBooklet.archive_url, {}, {
        preserveScroll: true,
        onSuccess: () => {
          archiveDialogOpen = false;
          selectedBooklet = null;
        }
      });
    }
    function modeLabel(mode) {
      return mode === "stored_questions" ? "Stored questions" : "Answer keys only";
    }
    function progressLabel(booklet) {
      if (booklet.mode === "stored_questions") {
        return `${booklet.question_count} of ${booklet.required_count} questions`;
      }
      if (booklet.sets.length === 0) return "No Set keys entered";
      const complete = booklet.sets.filter((set) => set.is_complete).length;
      return `${complete} of ${booklet.sets.length} Sets complete`;
    }
    function nextActionLabel(action, finalized) {
      if (action === "select_answer_sheet") return "Select Answer Sheet";
      if (action === "author_questions") return "Continue questions";
      if (action === "generate_sets") return "Generate Sets";
      if (action === "enter_answer_keys" || action === "complete_sets") return "Continue keys";
      if (action === "finalize") return "Review Booklet";
      return "Manage Booklet";
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("9kooce", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Question Booklets · Exam Materials</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              NewBookletDialog($$renderer5, { answer_sheets, can_view_question_content });
            };
            PageHeaderBar($$renderer4, {
              title: "Question Booklets",
              description: "Manage exam question booklets, subject sections, and verified answer keys.",
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (errors.booklet) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/35 dark:text-red-100">${escape_html(errors.booklet)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <section class="craft-card-depth flex flex-col gap-4 rounded-xl border border-border/70 p-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0 space-y-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-sm font-semibold">Alternate Booklet Sets</h2> `);
          Badge($$renderer4, {
            variant: enable_multi_set_booklets ? "default" : "secondary",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(enable_multi_set_booklets ? "Available for new drafts" : "Set A only by default")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <p id="booklet-set-policy-help" class="text-muted-foreground max-w-2xl text-sm">${escape_html(enable_multi_set_booklets ? "New Booklets still start with Set A. Staff must opt into A–D on each draft; finalized keys stay unchanged." : "Each Booklet uses its own Set A key. Existing finalized Sets and published allocations are retained.")}</p></div> <label class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2.5 text-sm font-medium select-none"><input type="checkbox" role="switch" class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"${attr("checked", enable_multi_set_booklets, true)}${attr("aria-checked", enable_multi_set_booklets)} aria-describedby="booklet-set-policy-help"${attr("disabled", setPolicySaving, true)}/> <span>Allow alternate Sets</span> `);
          {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></label></section> `);
          if (errors.policy) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(errors.policy)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (booklets.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"><div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl">`);
            Book_open($$renderer4, { class: "text-muted-foreground size-6" });
            $$renderer4.push(`<!----></div> <h2 class="mt-4 font-semibold">No Booklets yet</h2> <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">Create a Booklet to maintain answer keys for externally
                    printed test booklets (default) or author questions securely.</p> <div class="mt-5 flex justify-center">`);
            NewBookletDialog($$renderer4, { answer_sheets, can_view_question_content });
            $$renderer4.push(`<!----></div></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section class="space-y-3"><div class="flex items-center justify-between"><h2 class="text-muted-foreground flex items-center gap-2 text-xs font-bold tracking-wider uppercase"><span${attr_class(`size-2.5 rounded-full bg-emerald-500 ${officialBooklets().length ? "animate-pulse" : "opacity-50"}`)}></span> Official Booklets · In Production (${escape_html(officialBooklets().length)})</h2> `);
            if (officialBooklets().length > 0) {
              $$renderer4.push("<!--[0-->");
              Badge($$renderer4, {
                class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Ready for Exam Sessions`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (officialBooklets().length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"><!--[-->`);
              const each_array = ensure_array_like(officialBooklets());
              for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
                let booklet = each_array[$$index_1];
                $$renderer4.push(`<article class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
                MaterialDocumentPreview($$renderer4, {
                  src: booklet.thumbnail_url,
                  overlay: "booklet",
                  kind: booklet.mode,
                  title: booklet.name,
                  sets: booklet.sets,
                  alt: `${booklet.name} ${modeLabel(booklet.mode)} preview`
                });
                $$renderer4.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-start gap-2"><h3 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(booklet.name)}</h3> `);
                Badge($$renderer4, {
                  class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Official · In Production`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div> <div class="mt-1.5 flex flex-wrap items-center gap-2">`);
                if (booklet.mode === "stored_questions") {
                  $$renderer4.push("<!--[0-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      File_text($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Stored Questions &amp; Choices`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      Key_round($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Answer Key Only`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push(`<!----> <span class="text-xs text-muted-foreground">Questions external · Keys stored for scoring</span>`);
                }
                $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground mt-1 text-sm">${escape_html(progressLabel(booklet))} · ${escape_html(booklet.aptitude_count)}
                                        aptitude ${escape_html(booklet.aptitude_count === 1 ? "area" : "areas")}</p> `);
                if (booklet.answer_sheet) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-muted-foreground mt-3 text-xs">Paired Answer Sheet: <span class="font-medium text-foreground">${escape_html(booklet.answer_sheet)}</span></p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<p class="mt-3 text-xs font-medium text-amber-700 dark:text-amber-300">No Answer Sheet selected</p>`);
                }
                $$renderer4.push(`<!--]--> `);
                if (booklet.sets.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="mt-3 flex flex-wrap gap-1.5"${attr("aria-label", `${booklet.name} Sets`)}><!--[-->`);
                  const each_array_1 = ensure_array_like(booklet.sets);
                  for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                    let set = each_array_1[$$index];
                    $$renderer4.push(`<span${attr_class("border-border bg-background inline-flex min-h-7 items-center rounded-md border px-2 text-xs font-semibold", void 0, {
                      "text-emerald-700": set.is_complete,
                      "dark:text-emerald-300": set.is_complete,
                      "text-muted-foreground": !set.is_complete
                    })}>Set ${escape_html(set.code)}${escape_html(set.is_complete ? " · Complete" : " · Incomplete")}</span>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-2 sm:self-center">`);
                Link($$renderer4, {
                  href: booklet.url,
                  viewTransition: true,
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      variant: "outline",
                      class: "min-h-11 w-full sm:w-auto",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Open Booklet`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                if (booklet.can_archive && booklet.archive_url) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    class: "min-h-11 w-full sm:w-auto",
                    onclick: () => {
                      selectedBooklet = booklet;
                      archiveDialogOpen = true;
                    },
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Archive`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></article>`);
              }
              $$renderer4.push(`<!--]--></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="border-border bg-card/50 flex flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center"><p class="text-foreground text-sm font-medium">No finalized Booklets yet.</p> <p class="text-muted-foreground mt-1 max-w-sm text-xs leading-relaxed">Finalize an authored Booklet below to lock its question content and answer keys for exam session scheduling.</p></div>`);
            }
            $$renderer4.push(`<!--]--></section> <section class="space-y-3"><div class="flex items-center justify-between"><h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Drafts (${escape_html(draftBooklets().length)})</h2></div> `);
            if (draftBooklets().length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"><!--[-->`);
              const each_array_2 = ensure_array_like(draftBooklets());
              for (let $$index_3 = 0, $$length = each_array_2.length; $$index_3 < $$length; $$index_3++) {
                let booklet = each_array_2[$$index_3];
                $$renderer4.push(`<article class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
                MaterialDocumentPreview($$renderer4, {
                  src: booklet.thumbnail_url,
                  overlay: "booklet",
                  kind: booklet.mode,
                  title: booklet.name,
                  sets: booklet.sets,
                  alt: `${booklet.name} ${modeLabel(booklet.mode)} preview`
                });
                $$renderer4.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-start gap-2"><h3 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(booklet.name)}</h3> `);
                Badge($$renderer4, {
                  variant: "secondary",
                  class: "capitalize",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Draft`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div> <div class="mt-1.5 flex flex-wrap items-center gap-2">`);
                if (booklet.mode === "stored_questions") {
                  $$renderer4.push("<!--[0-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      File_text($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Stored Questions &amp; Choices`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      Key_round($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Answer Key Only`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push(`<!----> <span class="text-xs text-muted-foreground">Questions external · Keys stored for scoring</span>`);
                }
                $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground mt-1 text-sm">${escape_html(progressLabel(booklet))} · ${escape_html(booklet.aptitude_count)}
                                        aptitude ${escape_html(booklet.aptitude_count === 1 ? "area" : "areas")}</p> `);
                if (booklet.answer_sheet) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-muted-foreground mt-3 text-xs">Paired Answer Sheet: <span class="font-medium text-foreground">${escape_html(booklet.answer_sheet)}</span></p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<p class="mt-3 text-xs font-medium text-amber-700 dark:text-amber-300">No Answer Sheet selected</p>`);
                }
                $$renderer4.push(`<!--]--> `);
                if (booklet.sets.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="mt-3 flex flex-wrap gap-1.5"${attr("aria-label", `${booklet.name} Sets`)}><!--[-->`);
                  const each_array_3 = ensure_array_like(booklet.sets);
                  for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
                    let set = each_array_3[$$index_2];
                    $$renderer4.push(`<span${attr_class("border-border bg-background inline-flex min-h-7 items-center rounded-md border px-2 text-xs font-semibold", void 0, {
                      "text-emerald-700": set.is_complete,
                      "dark:text-emerald-300": set.is_complete,
                      "text-muted-foreground": !set.is_complete
                    })}>Set ${escape_html(set.code)}${escape_html(set.is_complete ? " · Complete" : " · Incomplete")}</span>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-2 sm:self-center">`);
                Link($$renderer4, {
                  href: booklet.url,
                  viewTransition: true,
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      variant: "outline",
                      class: "min-h-11 w-full sm:w-auto",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(nextActionLabel(booklet.next_action))}`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                if (booklet.can_discard && booklet.destroy_url) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    class: "min-h-11 w-full text-destructive hover:bg-destructive/10 sm:w-auto",
                    onclick: () => {
                      selectedBooklet = booklet;
                      discardDialogOpen = true;
                    },
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Discard`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></article>`);
              }
              $$renderer4.push(`<!--]--></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center"><div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl">`);
              Book_open($$renderer4, { class: "text-muted-foreground size-6" });
              $$renderer4.push(`<!----></div> <h3 class="mt-4 font-semibold">No Drafts in Progress</h3> <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">Create a Booklet to author questions securely or maintain
                            answer keys for externally prepared Sets.</p> <div class="mt-5 flex justify-center">`);
              NewBookletDialog($$renderer4, { answer_sheets, can_view_question_content });
              $$renderer4.push(`<!----></div></div>`);
            }
            $$renderer4.push(`<!--]--></section> `);
            if (archivedBooklets().length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<section class="space-y-3"><h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Archived Booklets · Retired (${escape_html(archivedBooklets().length)})</h2> <div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"><!--[-->`);
              const each_array_4 = ensure_array_like(archivedBooklets());
              for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                let booklet = each_array_4[$$index_4];
                $$renderer4.push(`<article class="grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">`);
                MaterialDocumentPreview($$renderer4, {
                  src: booklet.thumbnail_url,
                  overlay: "booklet",
                  kind: booklet.mode,
                  title: booklet.name,
                  sets: booklet.sets,
                  alt: `${booklet.name} ${modeLabel(booklet.mode)} preview`
                });
                $$renderer4.push(`<!----> <div class="min-w-0 self-stretch py-0.5"><div class="flex flex-wrap items-start gap-2"><h3 class="text-foreground text-base font-semibold sm:text-lg">${escape_html(booklet.name)}</h3> `);
                Badge($$renderer4, {
                  variant: "secondary",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Archived · Retired`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div> <div class="mt-1.5 flex flex-wrap items-center gap-2">`);
                if (booklet.mode === "stored_questions") {
                  $$renderer4.push("<!--[0-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      File_text($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Stored Questions`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                  Badge($$renderer4, {
                    variant: "outline",
                    class: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold",
                    children: ($$renderer5) => {
                      Key_round($$renderer5, { class: "size-3 mr-1" });
                      $$renderer5.push(`<!----> Answer Key Only`);
                    },
                    $$slots: { default: true }
                  });
                }
                $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground mt-1 text-sm">${escape_html(progressLabel(booklet))} · ${escape_html(booklet.aptitude_count)}
                                        aptitude ${escape_html(booklet.aptitude_count === 1 ? "area" : "areas")}</p></div> <div class="flex flex-wrap items-center gap-2 sm:self-center">`);
                Link($$renderer4, {
                  href: booklet.url,
                  viewTransition: true,
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      variant: "outline",
                      class: "min-h-11 w-full sm:w-auto",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->View Key`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                if (booklet.can_restore && booklet.restore_url) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    class: "min-h-11 w-full sm:w-auto",
                    onclick: () => router.post(booklet.restore_url, {}, { preserveScroll: true }),
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Restore to Official`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></article>`);
              }
              $$renderer4.push(`<!--]--></div></section>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (Dialog) {
              $$renderer4.push("<!--[-->");
              Dialog($$renderer4, {
                get open() {
                  return discardDialogOpen;
                },
                set open($$value) {
                  discardDialogOpen = $$value;
                  $$settled = false;
                },
                children: ($$renderer5) => {
                  if (Dialog_content) {
                    $$renderer5.push("<!--[-->");
                    Dialog_content($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_header) {
                          $$renderer6.push("<!--[-->");
                          Dialog_header($$renderer6, {
                            children: ($$renderer7) => {
                              if (Dialog_title) {
                                $$renderer7.push("<!--[-->");
                                Dialog_title($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Discard draft for ${escape_html(selectedBooklet?.name)}?`);
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
                                    $$renderer8.push(`<!---->All questions, keys, and configurations authored in this draft version will be permanently deleted. This action cannot be undone.`);
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
                                onclick: () => discardDialogOpen = false,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Cancel`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push(`<!----> `);
                              Button($$renderer7, {
                                variant: "destructive",
                                onclick: confirmDiscard,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Discard draft`);
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
            if (Dialog) {
              $$renderer4.push("<!--[-->");
              Dialog($$renderer4, {
                get open() {
                  return archiveDialogOpen;
                },
                set open($$value) {
                  archiveDialogOpen = $$value;
                  $$settled = false;
                },
                children: ($$renderer5) => {
                  if (Dialog_content) {
                    $$renderer5.push("<!--[-->");
                    Dialog_content($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_header) {
                          $$renderer6.push("<!--[-->");
                          Dialog_header($$renderer6, {
                            children: ($$renderer7) => {
                              if (Dialog_title) {
                                $$renderer7.push("<!--[-->");
                                Dialog_title($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Archive ${escape_html(selectedBooklet?.name)}?`);
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
                                    $$renderer8.push(`<!---->This will remove this booklet version from new exam session scheduling. Existing scheduled or completed exam sessions will continue to use their saved snapshot.`);
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
                                onclick: () => archiveDialogOpen = false,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Cancel`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push(`<!----> `);
                              Button($$renderer7, {
                                variant: "destructive",
                                onclick: confirmArchive,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Archive Booklet`);
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
  BookletsIndex as default
};
