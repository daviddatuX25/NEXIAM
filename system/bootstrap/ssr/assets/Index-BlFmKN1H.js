import { h as head, e as escape_html, b as attr, a as ensure_array_like, B as Button, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { F as File_upload } from "./file-upload-B9oVsy17.js";
import { F as File_spreadsheet } from "./settings-C5xOKpV3.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
import { H as History } from "./history-D15LFKXr.js";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      preset = null,
      academic_cycle = null,
      active_sessions = [],
      recent_batches = [],
      pending_batches = null,
      pending_approval_count = 0,
      awaiting_registrar_count = 0,
      awaiting_registrar_batches = null,
      capabilities = {},
      office_view_mode = null,
      view = null,
      specialized_imports = []
    } = $$props;
    const page = usePage();
    const operationCapabilities = {
      applicants_only: "registrar.applications.import",
      scores_only: "guidance.scoring.import",
      all_in_one: "guidance.scoring.import",
      multi_session: "guidance.exam.schedule"
    };
    const modeNames = {
      applicants_only: "Applicant intake",
      scores_only: "Exam scores",
      all_in_one: "Combined roster (advanced)",
      multi_session: "Exam sessions"
    };
    let selectedFile = null;
    const tab = derived(() => {
      const explicitView = ["upload", "pending", "history"].includes(view) ? view : null;
      const queryView = new URL(page.url, "http://localhost").searchParams.get("view");
      if (explicitView) return explicitView;
      if (["upload", "pending", "history"].includes(queryView)) return queryView;
      return office_view_mode === "registrar" && Number(pending_approval_count) > 0 ? "pending" : "upload";
    });
    let rejectionBatch = null;
    let rejectionReason = "";
    function can(mode) {
      const capability = operationCapabilities[mode];
      if (!capability) return false;
      if (Array.isArray(capabilities)) return capabilities.includes(capability);
      const capabilityByMode = {
        applicants_only: "can_import_applications",
        scores_only: "can_import_scores",
        all_in_one: "can_all_in_one",
        multi_session: "can_import_exam_sessions"
      };
      return Boolean(capabilities?.[capabilityByMode[mode]] ?? capabilities?.[capability] ?? capabilities?.[mode]);
    }
    const allowedModes = derived(() => [
      "applicants_only",
      "scores_only",
      "all_in_one",
      "multi_session"
    ].filter(can));
    const suggestedMode = derived(() => null);
    const suggestedAllowed = derived(() => Boolean(suggestedMode() && allowedModes().includes(suggestedMode())));
    const selectedMode = derived(() => suggestedAllowed() ? suggestedMode() : "");
    const templateType = derived(() => ["applicants_only", "scores_only", "all_in_one"].includes(selectedMode()) ? selectedMode() : can("applicants_only") ? "applicants_only" : can("scores_only") ? "scores_only" : can("all_in_one") ? "all_in_one" : null);
    const registrarApprovalInbox = derived(() => office_view_mode === "registrar");
    const pendingQueue = derived(() => registrarApprovalInbox() ? pending_batches : awaiting_registrar_batches);
    const pendingRows = derived(() => Array.isArray(pendingQueue()) ? pendingQueue() : pendingQueue()?.data ?? []);
    const pendingPageLinks = derived(() => Array.isArray(pendingQueue()?.links) ? pendingQueue().links : []);
    const pendingCount = derived(() => Number(registrarApprovalInbox() ? pending_approval_count : awaiting_registrar_count) || 0);
    function paginatorLabel(label) {
      return String(label ?? "").replaceAll("&laquo;", "‹").replaceAll("&raquo;", "›").replace(/<[^>]*>/g, "");
    }
    function formatDate(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.valueOf()) ? value : date.toLocaleString();
    }
    function batchLabel(batch) {
      return batch.import_type_label ?? modeNames[batch.import_type] ?? batch.import_type ?? "Import batch";
    }
    function statusLabel(batch) {
      const labels = {
        staged: "Saved for review",
        pending_approval: "Awaiting Registrar approval",
        awaiting_guidance: "Ready for Guidance",
        completed: "Completed",
        rejected: "Rejected",
        failed: "Needs attention"
      };
      return batch.status_label ?? labels[batch.status] ?? batch.status ?? "Saved";
    }
    const breadcrumbs = [{ label: "System", href: "/setup" }, { label: "Import Hub" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("d0hj27", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Import Hub · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<main class="mx-auto max-w-6xl min-w-0 space-y-6 p-4 sm:p-6"><header class="space-y-2"><p class="text-primary text-sm font-semibold">Data imports</p> <h1 class="text-foreground text-2xl font-bold tracking-tight">Import Hub</h1> <p class="text-muted-foreground max-w-3xl text-sm">Upload a spreadsheet and review its rows before anything changes
                in your records. You can find saved batches and approval work
                here anytime.</p> `);
          if (academic_cycle) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="border-border bg-card text-foreground inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">`);
            File_spreadsheet($$renderer4, { class: "text-primary size-4" });
            $$renderer4.push(`<!----> Academic cycle: <strong>${escape_html(academic_cycle.label ?? academic_cycle.name ?? "Current cycle")}</strong></p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<p class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">No academic cycle is available. Contact your administrator
                    before importing.</p>`);
          }
          $$renderer4.push(`<!--]--></header> <nav aria-label="Import views" class="border-border flex flex-wrap gap-2 border-b">`);
          Link($$renderer4, {
            href: "/imports?view=upload",
            class: `rounded-t-lg px-4 py-3 text-sm font-semibold ${tab() === "upload" ? "border-primary text-foreground border-b-2" : "text-muted-foreground hover:text-foreground"}`,
            "aria-current": tab() === "upload" ? "page" : void 0,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Upload`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: "/imports?view=pending",
            class: `rounded-t-lg px-4 py-3 text-sm font-semibold ${tab() === "pending" ? "border-primary text-foreground border-b-2" : "text-muted-foreground hover:text-foreground"}`,
            "aria-current": tab() === "pending" ? "page" : void 0,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Pending Approval <span class="bg-muted ml-1 rounded-full px-2 py-0.5 text-xs">${escape_html(pendingCount())}</span>`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: "/imports?view=history",
            class: `rounded-t-lg px-4 py-3 text-sm font-semibold ${tab() === "history" ? "border-primary text-foreground border-b-2" : "text-muted-foreground hover:text-foreground"}`,
            "aria-current": tab() === "history" ? "page" : void 0,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Batch History`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          if (pendingCount() > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<span class="ml-auto self-center rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-200">${escape_html(pendingCount())}
                    ${escape_html(registrarApprovalInbox() ? "need your review" : "awaiting Registrar approval")}</span>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></nav> `);
          if (tab() === "upload") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="border-border bg-card space-y-5 rounded-2xl border p-4 shadow-sm sm:p-6" aria-labelledby="upload-heading"><div><h2 id="upload-heading" class="text-lg font-bold">Start with your spreadsheet</h2> <p class="text-muted-foreground mt-1 text-sm">Auto-detect looks at the workbook and recommends a
                        suitable import. Its suggestion is guidance, not
                        approval.</p></div> `);
            File_upload($$renderer4, {
              label: "Choose or drop a spreadsheet",
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
            $$renderer4.push(`<!----> `);
            {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (academic_cycle?.id && templateType()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm"><a class="text-primary font-medium underline-offset-4 hover:underline"${attr("href", `/imports/templates/${templateType()}?academic_cycle_id=${academic_cycle.id}`)} download="">Download the ${escape_html(academic_cycle.label ?? academic_cycle.name ?? "current cycle")} spreadsheet template</a></p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section> <details class="border-border bg-card rounded-xl border"><summary class="flex cursor-pointer list-none items-center justify-between p-4 font-semibold">Other imports `);
            Chevron_down($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!----></summary> <div class="border-border grid gap-3 border-t p-4 sm:grid-cols-2">`);
            const each_array_4 = ensure_array_like(specialized_imports ?? []);
            if (each_array_4.length !== 0) {
              $$renderer4.push("<!--[-->");
              for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                let item = each_array_4[$$index_4];
                Link($$renderer4, {
                  href: item.href ?? item.url,
                  class: "border-border hover:border-primary/40 hover:bg-muted/30 rounded-xl border p-4 transition",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<h3 class="font-semibold">${escape_html(item.label)}</h3> <p class="text-muted-foreground mt-1 text-sm">${escape_html(item.description ?? `Open ${item.label}`)}</p> <p class="text-muted-foreground mt-3 text-xs">Accepted formats: ${escape_html((item.formats ?? []).join(", ") || "See destination")}</p>`);
                  },
                  $$slots: { default: true }
                });
              }
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push(`<p class="text-muted-foreground text-sm">No other import tools are available for your
                            account.</p>`);
            }
            $$renderer4.push(`<!--]--></div></details>`);
          } else if (tab() === "pending") {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<section class="space-y-4" aria-labelledby="pending-heading"><div><h2 id="pending-heading" class="text-lg font-bold">${escape_html(registrarApprovalInbox() ? "Pending Approval" : "Awaiting Registrar approval")}</h2> <p class="text-muted-foreground text-sm">${escape_html(registrarApprovalInbox() ? "Review applicant information and row checks before giving consent; exam scores are not part of Registrar review." : "These batches are waiting for Registrar consent. Once approved, Guidance can continue the exam-session and score workflow.")}</p></div> `);
            if (pendingRows().length === 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border rounded-xl border border-dashed p-8 text-center">`);
              Clock_3($$renderer4, { class: "text-muted-foreground mx-auto size-8" });
              $$renderer4.push(`<!----> <h3 class="mt-3 font-semibold">${escape_html(registrarApprovalInbox() ? "Nothing needs your approval" : "No batches awaiting Registrar approval")}</h3> <p class="text-muted-foreground mt-1 text-sm">${escape_html(registrarApprovalInbox() ? "New batches needing consent will appear here." : "Your batches awaiting consent will appear here.")}</p></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="grid gap-3"><!--[-->`);
              const each_array_5 = ensure_array_like(pendingRows());
              for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                let batch = each_array_5[$$index_5];
                $$renderer4.push(`<article class="border-border bg-card flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-semibold">${escape_html(batchLabel(batch))}</h3> <p class="text-muted-foreground mt-1 text-sm">${escape_html(batch.academic_cycle?.label ?? batch.academic_cycle_label ?? academic_cycle?.label ?? "Academic cycle")} · ${escape_html(batch.total_rows ?? batch.row_count ?? "—")} rows · ${escape_html(formatDate(batch.created_at))}</p> <p class="text-muted-foreground mt-1 text-xs font-medium">${escape_html(registrarApprovalInbox() ? "Needs your review" : "Awaiting Registrar approval")}</p></div> <div class="flex flex-wrap gap-2">`);
                Link($$renderer4, {
                  href: `/imports/${batch.id}/review`,
                  class: "bg-primary text-primary-foreground inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(registrarApprovalInbox() ? "Review applicants" : "Track batch")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!---->`);
                if (registrarApprovalInbox()) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    onclick: () => {
                      rejectionBatch = batch;
                      rejectionReason = "";
                    },
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Reject batch`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></article>`);
              }
              $$renderer4.push(`<!--]--></div> `);
              if (pendingPageLinks().length > 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<nav aria-label="Pending approval pages" class="flex flex-wrap gap-2"><!--[-->`);
                const each_array_6 = ensure_array_like(pendingPageLinks());
                for (let index = 0, $$length = each_array_6.length; index < $$length; index++) {
                  let pageLink = each_array_6[index];
                  if (pageLink.url) {
                    $$renderer4.push("<!--[0-->");
                    Link($$renderer4, {
                      href: pageLink.url,
                      preserveState: true,
                      preserveScroll: true,
                      "aria-current": pageLink.active ? "page" : void 0,
                      class: `border-border inline-flex min-h-10 items-center rounded-md border px-3 text-sm ${pageLink.active ? "bg-primary text-primary-foreground" : "hover:bg-muted"}`,
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->${escape_html(paginatorLabel(pageLink.label))}`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]-->`);
                }
                $$renderer4.push(`<!--]--></nav>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            }
            $$renderer4.push(`<!--]--></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section class="space-y-4" aria-labelledby="history-heading"><div><h2 id="history-heading" class="text-lg font-bold">Batch History</h2> <p class="text-muted-foreground text-sm">Recent saved imports and their current status. Pending
                        approval work is listed separately.</p></div> `);
            if (recent_batches.length === 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border rounded-xl border border-dashed p-8 text-center">`);
              History($$renderer4, { class: "text-muted-foreground mx-auto size-8" });
              $$renderer4.push(`<!----> <h3 class="mt-3 font-semibold">No recent batches</h3> <p class="text-muted-foreground mt-1 text-sm">After a spreadsheet is saved, its tracking link will
                            appear here.</p></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="border-border overflow-x-auto rounded-xl border"><table class="w-full min-w-144 text-left text-sm"><thead class="bg-muted/50 text-muted-foreground"><tr><th class="p-3 font-semibold">Import</th><th class="p-3 font-semibold">Academic cycle</th><th class="p-3 font-semibold">Rows</th><th class="p-3 font-semibold">Status</th><th class="p-3 font-semibold">Saved</th><th class="p-3"><span class="sr-only">Batch action</span></th></tr></thead><tbody class="divide-border divide-y"><!--[-->`);
              const each_array_7 = ensure_array_like(recent_batches);
              for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                let batch = each_array_7[$$index_7];
                $$renderer4.push(`<tr><td class="p-3 font-medium">${escape_html(batchLabel(batch))}</td><td class="text-muted-foreground p-3">${escape_html(batch.academic_cycle?.label ?? batch.academic_cycle_label ?? "—")}</td><td class="p-3">${escape_html(batch.total_rows ?? batch.row_count ?? "—")}</td><td class="p-3">${escape_html(statusLabel(batch))}</td><td class="text-muted-foreground p-3">${escape_html(formatDate(batch.created_at))}</td><td class="p-3">`);
                Link($$renderer4, {
                  href: `/imports/${batch.id}/review`,
                  class: "text-primary font-semibold hover:underline",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Open review`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></td></tr>`);
              }
              $$renderer4.push(`<!--]--></tbody></table></div>`);
            }
            $$renderer4.push(`<!--]--></section>`);
          }
          $$renderer4.push(`<!--]--></main> `);
          if (rejectionBatch) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation"><section role="dialog" aria-modal="true" aria-labelledby="reject-title" class="border-border bg-card w-full max-w-lg space-y-4 rounded-xl border p-5 shadow-xl"><h2 id="reject-title" class="text-lg font-bold">Reject this batch?</h2> <p class="text-muted-foreground text-sm">Give a reason so the sending office knows what needs
                    attention.</p> <label for="reject-reason" class="block text-sm font-medium">Reason for rejection</label> <textarea id="reject-reason" rows="4" class="border-input bg-background w-full rounded-md border p-3 text-sm" required="">`);
            const $$body = escape_html(rejectionReason);
            if ($$body) {
              $$renderer4.push(`${$$body}`);
            }
            $$renderer4.push(`</textarea> <div class="flex justify-end gap-2">`);
            Button($$renderer4, {
              variant: "outline",
              onclick: () => {
                rejectionBatch = null;
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Cancel`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              variant: "destructive",
              disabled: !rejectionReason.trim(),
              onclick: () => {
                router.post(`/imports/${rejectionBatch.id}/reject`, { reason: rejectionReason.trim() }, {
                  onSuccess: () => {
                    rejectionBatch = null;
                    rejectionReason = "";
                  }
                });
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Reject batch`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></section></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]-->`);
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
  Index as default
};
