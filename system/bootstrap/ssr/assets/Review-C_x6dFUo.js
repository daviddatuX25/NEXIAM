import { h as head, e as escape_html, b as attr, B as Button, a as ensure_array_like, c as attr_class, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { F as File_spreadsheet } from "./settings-C5xOKpV3.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
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
import "./badge-D9Vgs7xh.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function Review($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      batch = {},
      rows = {},
      review_mode = "standard",
      capabilities = {},
      office_view_mode = null,
      can_approve = false,
      can_resume = false
    } = $$props;
    let selectedRowIds = [];
    let rejectionOpen = false;
    let rejectionReason = "";
    let submitting = false;
    let actionError = "";
    const rowItems = derived(() => Array.isArray(rows) ? rows : rows?.data ?? []);
    const eligibleRows = derived(() => rowItems().filter((row) => row.eligible === true || row.is_eligible === true || row.status === "valid").map((row) => String(row.id)));
    const selectedEligible = derived(() => selectedRowIds);
    const allEligibleSelected = derived(() => eligibleRows().length > 0 && eligibleRows().every((id) => selectedEligible().includes(id)));
    const pageLinks = derived(() => Array.isArray(rows?.links) ? rows.links : []);
    const isRegistrarOffice = derived(() => office_view_mode === "registrar");
    const isRegistrar = derived(() => ["registrar_consent", "applicant_consent"].includes(review_mode) || isRegistrarOffice() && !["guidance_execution"].includes(review_mode));
    const isGuidance = derived(() => review_mode === "guidance_execution" || can_resume || capabilities?.can_resume || capabilities?.can_view_scores || capabilities?.can_import_scores);
    const canApprove = derived(() => Boolean(can_approve || capabilities?.can_approve || capabilities?.["registrar.applications.import"] || capabilities?.approve_applicant_import));
    const canResume = derived(() => Boolean(can_resume || capabilities?.can_resume || capabilities?.["guidance.scoring.import"] || capabilities?.resume_score_import));
    const canCommit = derived(() => Boolean(capabilities?.can_commit || capabilities?.["guidance.scoring.import"] || capabilities?.["registrar.applications.import"] || capabilities?.commit_import));
    const canSelectRows = derived(() => canApprove() || canCommit());
    const canDownloadErrors = derived(() => Boolean(capabilities?.can_download_errors));
    const title = derived(() => isRegistrar() ? "Review applicant intake" : isGuidance() ? "Review exam scores" : "Review import");
    function paginatorLabel(label) {
      return String(label ?? "").replaceAll("&laquo;", "‹").replaceAll("&raquo;", "›").replace(/<[^>]*>/g, "");
    }
    function statusLabel(status) {
      const labels = {
        staged: "Saved for review",
        pending_approval: "Awaiting Registrar approval",
        awaiting_guidance: "Ready for Guidance",
        valid: "Ready",
        invalid: "Needs correction",
        duplicate: "Possible duplicate",
        skipped: "Not selected",
        completed: "Completed",
        rejected: "Rejected",
        failed: "Needs attention"
      };
      return labels[status] ?? String(status ?? "Unknown status").replaceAll("_", " ");
    }
    function selectAllEligible() {
      selectedRowIds = allEligibleSelected() ? selectedRowIds.filter((id) => !eligibleRows().includes(id)) : [.../* @__PURE__ */ new Set([...selectedRowIds, ...eligibleRows()])];
    }
    function submitApproval() {
      if (!canApprove() || selectedEligible().length === 0) return;
      submitting = true;
      actionError = "";
      router.post(`/imports/${batch.id}/approve`, { selected_row_ids: selectedEligible() }, {
        onError: () => {
          actionError = "Approval could not be completed. Review the messages and try again.";
        },
        onFinish: () => {
          submitting = false;
        }
      });
    }
    function submitResume() {
      if (!canResume()) return;
      submitting = true;
      actionError = "";
      router.post(`/imports/${batch.id}/resume`, {}, {
        onError: () => {
          actionError = "This batch could not continue. Review its row messages and try again.";
        },
        onFinish: () => {
          submitting = false;
        }
      });
    }
    function submitCommit() {
      if (!canCommit() || selectedEligible().length === 0) return;
      submitting = true;
      actionError = "";
      router.post(`/imports/${batch.id}/commit`, { selected_row_ids: selectedEligible() }, {
        onError: () => {
          actionError = "This import could not be processed. Review its row messages and try again.";
        },
        onFinish: () => {
          submitting = false;
        }
      });
    }
    function rejectBatch() {
      if (!rejectionReason.trim()) return;
      submitting = true;
      router.post(`/imports/${batch.id}/reject`, { reason: rejectionReason.trim() }, {
        onError: () => {
          actionError = "The batch could not be rejected. Try again.";
        },
        onSuccess: () => {
          rejectionOpen = false;
          rejectionReason = "";
        },
        onFinish: () => {
          submitting = false;
        }
      });
    }
    function applicantFields(row) {
      return Object.entries(row.applicant_fields ?? row.fields ?? row.applicant ?? {}).filter(([, value]) => value !== null && value !== "");
    }
    function scoreFields(row) {
      return row.scores ?? row.score_values ?? {};
    }
    const breadcrumbs = [{ label: "Imports", href: "/imports" }, { label: title() }];
    head("j75p27", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(title())} · NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<main class="mx-auto max-w-6xl min-w-0 space-y-6 p-4 sm:p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div class="space-y-2">`);
        Link($$renderer3, {
          href: "/imports",
          class: "text-primary text-sm font-medium hover:underline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->← Back to Import Hub`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <h1 class="text-2xl font-bold tracking-tight">${escape_html(title())}</h1> <p class="text-muted-foreground text-sm">Saved spreadsheet rows for ${escape_html(batch.academic_cycle?.label ?? batch.academic_cycle_label ?? "this academic cycle")}. Review the source and row status
                    before taking action.</p></div> <div class="flex flex-wrap items-center gap-3"><div class="border-border bg-card rounded-xl border p-3 text-sm"><div class="flex items-center gap-2 font-semibold">`);
        File_spreadsheet($$renderer3, { class: "text-primary size-4" });
        $$renderer3.push(`<!---->${escape_html(batch.file_name ?? batch.filename ?? `Batch ${batch.id}`)}</div> <p class="text-muted-foreground mt-1 text-xs">${escape_html(batch.import_type_label ?? batch.import_type ?? "Import")} · ${escape_html(batch.status_label ?? statusLabel(batch.status))} · ${escape_html(batch.total_rows ?? rows?.total ?? rowItems().length)} rows</p></div> `);
        if (canDownloadErrors()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<a${attr("href", `/imports/${batch.id}/errors`)} download="" class="text-primary text-sm font-medium underline-offset-4 hover:underline">Download row errors</a>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div> `);
        if (batch.receipt) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="border-primary/20 bg-primary/5 rounded-xl border p-4"><h2 class="font-semibold">Batch tracking</h2> <p class="text-muted-foreground mt-1 text-sm">${escape_html(batch.receipt)}</p></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (actionError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="alert" class="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border p-3 text-sm">${escape_html(actionError)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (isRegistrar()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<aside class="border-primary/20 bg-primary/5 rounded-xl border p-4 text-sm"><strong>Applicant consent review</strong> <p class="text-muted-foreground mt-1">You can review applicant information and row checks.
                    Assessment scores are not part of Registrar review.</p></aside>`);
        } else if (isGuidance()) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<aside class="border-primary/20 bg-primary/5 rounded-xl border p-4 text-sm"><strong>Guidance score review</strong> <p class="text-muted-foreground mt-1">Review score information and row checks before continuing
                    this phase.</p></aside>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (isGuidance() && batch.status === "pending_approval") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<aside class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm"><strong>Waiting for Registrar approval</strong> <p class="text-muted-foreground mt-1">New applicant records will not be created until the
                    Registrar reviews and approves eligible rows. Once approved,
                    Guidance can continue exam-session assignment and score
                    processing.</p></aside>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <section class="border-border bg-card overflow-hidden rounded-xl border"><div class="border-border flex flex-wrap items-center justify-between gap-3 border-b p-4"><div><h2 class="font-bold">Rows in this batch</h2> <p class="text-muted-foreground mt-1 text-sm">`);
        if (canSelectRows()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`${escape_html(selectedEligible().length)} eligible row${escape_html(selectedEligible().length === 1 ? "" : "s")} selected`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`${escape_html(rows?.total ?? rowItems().length)} rows in this batch`);
        }
        $$renderer3.push(`<!--]--></p></div> `);
        if (canSelectRows()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex items-center gap-2">`);
          if (selectedRowIds.length > 0) {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              type: "button",
              variant: "ghost",
              size: "sm",
              class: "text-xs text-destructive hover:bg-destructive/10",
              onclick: () => selectedRowIds = [],
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Clear selection`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            disabled: eligibleRows().length === 0,
            onclick: selectAllEligible,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(allEligibleSelected() ? "Deselect visible" : "Select all eligible on this page")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (rowItems().length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="p-10 text-center">`);
          Clock_3($$renderer3, { class: "text-muted-foreground mx-auto size-8" });
          $$renderer3.push(`<!----> <h3 class="mt-3 font-semibold">No rows to show</h3> <p class="text-muted-foreground mt-1 text-sm">This batch may be empty or its rows are not available.</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="divide-border divide-y"><!--[-->`);
          const each_array = ensure_array_like(rowItems());
          for (let $$index_4 = 0, $$length = each_array.length; $$index_4 < $$length; $$index_4++) {
            let row = each_array[$$index_4];
            $$renderer3.push(`<article class="space-y-3 p-4"><div class="flex flex-wrap items-start gap-3"><input${attr("id", `row-${row.id}`)} type="checkbox"${attr("checked", selectedRowIds.includes(String(row.id)), true)}${attr("disabled", !canSelectRows() || !eligibleRows().includes(String(row.id)), true)}${attr_class(`accent-primary mt-1 size-4 ${canSelectRows() ? "" : "hidden"}`)}${attr("aria-label", `Select eligible row ${row.source?.row_index ?? row.row_index ?? row.id}`)}/> <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">Source row ${escape_html(row.source?.row_index ?? row.row_index ?? row.source_row_number ?? row.id)}</h3> <span class="border-border rounded-full border px-2 py-0.5 text-xs font-medium">${escape_html(row.status_label ?? statusLabel(row.status))}</span>`);
            if (row.eligible || row.is_eligible || row.status === "valid") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Eligible</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground mt-1 text-xs">Sheet: ${escape_html(row.source?.sheet_name ?? row.sheet_name ?? row.source_sheet ?? "—")}</p></div></div> `);
            if (isRegistrar()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<dl class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2">`);
              const each_array_1 = ensure_array_like(applicantFields(row));
              if (each_array_1.length !== 0) {
                $$renderer3.push("<!--[-->");
                for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                  let [field, value] = each_array_1[$$index];
                  $$renderer3.push(`<div class="min-w-0"><dt class="text-muted-foreground text-xs">${escape_html(field.replaceAll("_", " "))}</dt> <dd class="text-sm break-words">${escape_html(value)}</dd></div>`);
                }
              } else {
                $$renderer3.push("<!--[!-->");
                $$renderer3.push(`<p class="text-muted-foreground text-sm">No applicant details are available
                                            for this row.</p>`);
              }
              $$renderer3.push(`<!--]--></dl>`);
            } else if (isGuidance()) {
              $$renderer3.push("<!--[1-->");
              if (Object.keys(scoreFields(row) ?? {}).length) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<dl class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2"><!--[-->`);
                const each_array_2 = ensure_array_like(Object.entries(scoreFields(row) ?? {}));
                for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                  let [field, value] = each_array_2[$$index_1];
                  $$renderer3.push(`<div><dt class="text-muted-foreground text-xs">${escape_html(field.replaceAll("_", " "))}</dt> <dd class="text-sm font-medium">${escape_html(value ?? "—")}</dd></div>`);
                }
                $$renderer3.push(`<!--]--></dl>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]-->`);
              if (row.applicant_display_name) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="text-sm">Applicant: <strong>${escape_html(row.applicant_display_name)}</strong></p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]-->`);
            } else if (row.display_fields) {
              $$renderer3.push("<!--[2-->");
              $$renderer3.push(`<dl class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2"><!--[-->`);
              const each_array_3 = ensure_array_like(Object.entries(row.display_fields));
              for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
                let [field, value] = each_array_3[$$index_2];
                $$renderer3.push(`<div><dt class="text-muted-foreground text-xs">${escape_html(field.replaceAll("_", " "))}</dt> <dd class="text-sm break-words">${escape_html(value ?? "—")}</dd></div>`);
              }
              $$renderer3.push(`<!--]--></dl>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (row.errors?.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-destructive/20 bg-destructive/5 rounded-lg border p-3"><p class="text-destructive flex items-center gap-2 text-sm font-semibold">`);
              Circle_alert($$renderer3, { class: "size-4" });
              $$renderer3.push(`<!---->Row needs
                                        attention</p> <ul class="text-muted-foreground mt-2 list-inside list-disc space-y-1 text-sm"><!--[-->`);
              const each_array_4 = ensure_array_like(row.errors);
              for (let index = 0, $$length2 = each_array_4.length; index < $$length2; index++) {
                let error = each_array_4[index];
                $$renderer3.push(`<li>${escape_html(typeof error === "string" ? error : error.message ?? error.label ?? JSON.stringify(error))}</li>`);
              }
              $$renderer3.push(`<!--]--></ul></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></article>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (pageLinks().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<nav aria-label="Batch row pages" class="border-border flex flex-wrap items-center justify-between gap-2 border-t p-4"><!--[-->`);
          const each_array_5 = ensure_array_like(pageLinks());
          for (let index = 0, $$length = each_array_5.length; index < $$length; index++) {
            let pageLink = each_array_5[index];
            if (pageLink.url) {
              $$renderer3.push("<!--[0-->");
              Link($$renderer3, {
                href: pageLink.url,
                preserveState: true,
                preserveScroll: true,
                "aria-current": pageLink.active ? "page" : void 0,
                class: `border-border inline-flex min-h-10 items-center gap-1 rounded-md border px-3 text-sm ${pageLink.active ? "bg-primary text-primary-foreground" : "hover:bg-muted"}`,
                children: ($$renderer4) => {
                  if (String(pageLink.label).toLowerCase().includes("previous")) {
                    $$renderer4.push("<!--[0-->");
                    Chevron_left($$renderer4, { class: "size-4" });
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]-->${escape_html(paginatorLabel(pageLink.label))}`);
                  if (String(pageLink.label).toLowerCase().includes("next")) {
                    $$renderer4.push("<!--[0-->");
                    Chevron_right($$renderer4, { class: "size-4" });
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<span class="text-muted-foreground inline-flex min-h-10 items-center rounded-md px-3 text-sm" aria-disabled="true">${escape_html(paginatorLabel(pageLink.label))}</span>`);
            }
            $$renderer3.push(`<!--]-->`);
          }
          $$renderer3.push(`<!--]--></nav>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></section> `);
        if (isRegistrar() && canApprove()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-muted-foreground text-sm">Only the rows you explicitly check will receive applicant
                    consent. An empty selection cannot be approved.</p> <div class="flex flex-wrap gap-2">`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            onclick: () => {
              rejectionOpen = true;
              rejectionReason = "";
            },
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Reject batch`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            type: "button",
            disabled: submitting || selectedEligible().length === 0,
            onclick: submitApproval,
            children: ($$renderer4) => {
              if (submitting) {
                $$renderer4.push("<!--[0-->");
                Refresh_cw($$renderer4, { class: "mr-2 size-4 animate-spin" });
                $$renderer4.push(`<!---->Approving…`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`Approve applicant intake (${escape_html(selectedEligible().length)})`);
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section>`);
        } else if (isGuidance() && canResume()) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<section class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-muted-foreground text-sm">This continues every applicant row approved by the
                    Registrar. The operation is batch-wide, so no row checkboxes
                    are needed here.</p> `);
          Button($$renderer3, {
            type: "button",
            disabled: submitting || Number(batch.guidance_pending_rows ?? batch.imported_rows ?? eligibleRows().length) === 0,
            onclick: submitResume,
            children: ($$renderer4) => {
              if (submitting) {
                $$renderer4.push("<!--[0-->");
                Refresh_cw($$renderer4, { class: "mr-2 size-4 animate-spin" });
                $$renderer4.push(`<!---->Continuing…`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`Continue approved rows (${escape_html(batch.guidance_pending_rows ?? batch.imported_rows ?? eligibleRows().length)})`);
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></section>`);
        } else if (review_mode === "standard" && canCommit()) {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<section class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-muted-foreground text-sm">Select eligible rows to process this saved import.</p> `);
          Button($$renderer3, {
            type: "button",
            disabled: submitting || selectedEligible().length === 0,
            onclick: submitCommit,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Process selected rows (${escape_html(selectedEligible().length)})`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">You can review this batch, but your current permissions do not
                allow its next action.</p>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (rejectionOpen) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation"><section role="dialog" aria-modal="true" aria-labelledby="reject-title" class="border-border bg-card w-full max-w-lg space-y-4 rounded-xl border p-5 shadow-xl"><h2 id="reject-title" class="text-lg font-bold">Reject batch</h2> <p class="text-muted-foreground text-sm">Explain why this applicant batch cannot be approved. A
                        reason is required.</p> <label for="rejection-reason" class="text-sm font-medium">Reason</label><textarea id="rejection-reason" rows="4" class="border-input bg-background w-full rounded-md border p-3 text-sm" required="">`);
          const $$body = escape_html(rejectionReason);
          if ($$body) {
            $$renderer3.push(`${$$body}`);
          }
          $$renderer3.push(`</textarea> <div class="flex justify-end gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            onclick: () => {
              rejectionOpen = false;
            },
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            variant: "destructive",
            disabled: !rejectionReason.trim() || submitting,
            onclick: rejectBatch,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Reject batch`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></main>`);
      }
    });
  });
}
export {
  Review as default
};
