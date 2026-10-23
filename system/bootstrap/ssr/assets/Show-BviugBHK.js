import { h as head, e as escape_html, B as Button, c as attr_class, a as ensure_array_like, b as attr, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout, a as Send } from "./StaffLayout-CR-MZopa.js";
/* empty css                                                        */
import "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { U as Users } from "./users-DWlguTa_.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { B as Bot } from "./bot-LOOyhTeB.js";
import { B as Ban } from "./ban-CUQ_jw2o.js";
import { K as Key } from "./key-C6JS4UCQ.js";
import { C as Copy } from "./copy-D9_YDhto.js";
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
import "./dialog-description-D12L0QA9.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      source = {},
      version = null,
      message = null,
      current_office = null,
      capabilities = {
        manage: false,
        approve: false,
        approve_current_version: false,
        cross_office_approve: false,
        establish_cross_office: false,
        establish_cross_office_current_version: false,
        publish_current_version: false,
        revoke_current_version: false,
        extract_current_version: false
      }
    } = $$props;
    const page = usePage();
    const flashStatus = derived(() => page.props.flash?.status ?? null);
    const flashError = derived(() => page.props.flash?.error ?? null);
    const isCrossOffice = derived(() => source?.audience === "cross_office_staff");
    const isFaq = derived(() => source?.source_class === "public_faq");
    const officeApprovals = derived(() => Array.isArray(version?.cross_office_approvals) ? version.cross_office_approvals : []);
    const requiredJointOffices = [
      { key: "registrar", label: "Registrar Office" },
      { key: "guidance", label: "Guidance Office" }
    ];
    const signedJointOfficeCount = derived(() => requiredJointOffices.filter((office) => approvalForOffice(office.key)).length);
    const jointApprovalComplete = derived(() => signedJointOfficeCount() === requiredJointOffices.length);
    const canApproveVersion = derived(() => isCrossOffice() ? capabilities?.cross_office_approve === true : capabilities?.approve_current_version === true);
    const canEstablishCrossOffice = derived(() => source?.audience === "staff_only" && version?.status === "review_required" && capabilities?.establish_cross_office_current_version === true);
    const canPublishVersion = derived(() => capabilities?.approve === true && ["approved", "index_failed"].includes(version?.status) && (!isCrossOffice() || jointApprovalComplete()));
    const canExtract = derived(() => capabilities?.manage && ["draft", "rejected", "index_failed"].includes(version?.status));
    const owningOffice = derived(() => officeLabel(source?.owning_office));
    let revokeReason = "";
    function officeLabel(office) {
      if (!office) return "Unassigned";
      if (office === "registrar") return "Registrar";
      if (office === "guidance") return "Guidance";
      if (typeof office === "object") return office.label ?? office.name ?? office.code ?? "Unassigned";
      return String(office);
    }
    function normalizeOffice(office) {
      const value = typeof office === "object" && office !== null ? office.code ?? office.slug ?? office.name ?? office.label : office;
      const normalized = String(value ?? "").toLowerCase();
      if (normalized.includes("registrar")) return "registrar";
      if (normalized.includes("guidance")) return "guidance";
      return normalized;
    }
    function approvalForOffice(officeKey) {
      return officeApprovals().find((approval) => normalizeOffice(approval.office) === officeKey) ?? null;
    }
    function formatTimestamp(value) {
      if (typeof value !== "string" || value === "") return "Not recorded";
      const timestamp = new Date(value);
      if (Number.isNaN(timestamp.getTime())) return value;
      return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit"
      }).format(timestamp);
    }
    function statusConfig(status) {
      switch (status) {
        case "active":
          return {
            label: "Active & Published",
            badgeClass: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20",
            dotClass: "bg-emerald-500",
            icon: Sparkles,
            desc: "Live in Xiam RAG retrieval and cited for applicant queries."
          };
        case "review_required":
          return {
            label: "Needs Review",
            badgeClass: "bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20",
            dotClass: "bg-amber-500",
            icon: Clock,
            desc: "Awaiting independent office-head review and cryptographic signoff."
          };
        case "approved":
          return {
            label: "Approved (Ready to Publish)",
            badgeClass: "bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20",
            dotClass: "bg-sky-500",
            icon: Shield_check,
            desc: "Signed off. An authorized approver can now publish this version to the corpus."
          };
        case "draft":
          return {
            label: "Draft",
            badgeClass: "bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/20",
            dotClass: "bg-slate-400",
            icon: File_text,
            desc: "Draft version. Extraction must be initiated to prepare digest fixtures."
          };
        case "extracting":
        case "indexing":
          return {
            label: status === "indexing" ? "Indexing into Corpus..." : "Extracting Knowledge...",
            badgeClass: "bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20 animate-pulse",
            dotClass: "bg-purple-500",
            icon: Refresh_cw,
            desc: "Background processing in progress. Please refresh in a few moments."
          };
        case "revoked":
          return {
            label: "Revoked",
            badgeClass: "bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20",
            dotClass: "bg-rose-500",
            icon: Ban,
            desc: "Revoked by authority. Excluded from all search indices and retrieval."
          };
        case "index_failed":
        case "rejected":
          return {
            label: status === "index_failed" ? "Index Failed" : "Rejected",
            badgeClass: "bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20",
            dotClass: "bg-red-500",
            icon: Circle_alert,
            desc: "Version could not be verified or processed. Review the error details below."
          };
        default:
          return {
            label: status ? status.replaceAll("_", " ") : "Unknown",
            badgeClass: "bg-muted text-muted-foreground border-border",
            dotClass: "bg-muted-foreground",
            icon: File_text,
            desc: "Lifecycle status unrecognized."
          };
      }
    }
    const currentStep = derived(() => {
      if (!version) return 1;
      if (version.status === "draft") return 1;
      if (["extracting", "review_required"].includes(version.status)) return 2;
      if (version.status === "approved") return 3;
      if (version.status === "active") return 4;
      return 2;
    });
    function extract() {
      router.post(`/knowledge/${source.id}/extract`, { version_id: version.id });
    }
    function establishCrossOffice() {
      router.post(`/knowledge/${source.id}/cross-office`, { version_id: version.id });
    }
    function approve() {
      router.post(`/knowledge/${source.id}/approve`, { version_id: version.id });
    }
    function publish() {
      router.post(`/knowledge/${source.id}/publish`, { version_id: version.id });
    }
    function revoke() {
      if (!confirm("Revoke this active version? Knowledge retrieval will deny immediately.")) return;
      router.post(`/knowledge/${source.id}/revoke`, { version_id: version.id, reason: revokeReason || "revoked" });
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "FAQs & Internal Procedures", href: "/knowledge" },
      {
        label: page_state === "denied" ? "Unavailable" : source?.title ?? "Item"
      }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("wkqcrt", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(page_state === "denied" ? "Source Unavailable" : source?.title ?? "Knowledge Detail")} · Xiam Knowledge &amp; Rules · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8"><div class="border-border/70 flex flex-col justify-between gap-4 border-b pb-4 sm:flex-row sm:items-center"><div class="flex items-center gap-3">`);
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
          $$renderer4.push(`<!----> <div class="bg-border/80 h-4 w-px"></div> <span class="text-muted-foreground bg-muted/60 border-border/60 rounded border px-2 py-0.5 font-mono text-xs">${escape_html(source?.key ?? "—")}</span></div> `);
          if (version && page_state === "ready") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
            if (canExtract()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                size: "sm",
                variant: "outline",
                class: "min-h-9 gap-1.5 text-xs",
                onclick: extract,
                children: ($$renderer5) => {
                  Refresh_cw($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> Run Extraction`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (source.audience === "staff_only" && version.status === "review_required" && canEstablishCrossOffice()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                size: "sm",
                variant: "outline",
                class: "min-h-9 gap-1.5 text-xs",
                onclick: establishCrossOffice,
                children: ($$renderer5) => {
                  Users($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> Establish Cross-Office Scope`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (version.status === "review_required" && canApproveVersion()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                size: "sm",
                class: "min-h-9 gap-1.5 bg-amber-600 text-xs text-white shadow-xs hover:bg-amber-700",
                onclick: approve,
                children: ($$renderer5) => {
                  Circle_check($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> ${escape_html(isCrossOffice() ? "Sign For My Office" : "Approve Version")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (canPublishVersion()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                size: "sm",
                class: "min-h-9 gap-1.5 bg-emerald-600 text-xs text-white shadow-xs hover:bg-emerald-700",
                onclick: publish,
                children: ($$renderer5) => {
                  Send($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> Publish to Active Corpus`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (flashStatus()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="status" class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300">`);
            Circle_check($$renderer4, { class: "size-4 shrink-0" });
            $$renderer4.push(`<!----> <span>${escape_html(flashStatus())}</span></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (flashError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/40 bg-destructive/10 text-destructive flex items-center gap-2 rounded-xl border px-4 py-3 text-sm">`);
            Circle_alert($$renderer4, { class: "size-4 shrink-0" });
            $$renderer4.push(`<!----> <span>${escape_html(flashError())}</span></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-8 text-center">`);
            Lock($$renderer4, { class: "text-destructive/80 mx-auto size-9" });
            $$renderer4.push(`<!----> <h3 class="text-foreground text-base font-semibold">Access Restricted</h3> <p class="text-muted-foreground mx-auto max-w-md text-sm">You do not have authorization to view this knowledge source
                    or its owning office scope.</p></div>`);
          } else if (page_state === "conflict") {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div role="alert" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200"><p class="font-medium">Version conflict detected</p> <p class="mt-1 text-xs">This source was updated by another staff member while you
                    were inspecting it. Please refresh before proceeding.</p></div>`);
          } else if (["failed", "error"].includes(page_state)) {
            $$renderer4.push("<!--[2-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/40 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm">${escape_html(message ?? "Knowledge source details could not be loaded.")}</div>`);
          } else if (!version) {
            $$renderer4.push("<!--[3-->");
            $$renderer4.push(`<div class="border-border bg-card text-muted-foreground rounded-xl border p-6 text-center text-sm">No active version is available for this knowledge source.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            const status = statusConfig(version.status);
            $$renderer4.push(`<section class="border-border/80 bg-card space-y-6 rounded-2xl border p-5 shadow-xs sm:p-6 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"><div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"><div class="space-y-1.5"><div class="flex items-center gap-2"><span class="bg-muted/80 text-foreground border-border/60 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase">`);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              Circle_question_mark($$renderer4, { class: "size-3 text-purple-600 dark:text-purple-400" });
              $$renderer4.push(`<!----> Applicant FAQ`);
            } else {
              $$renderer4.push("<!--[-1-->");
              File_text($$renderer4, { class: "size-3 text-sky-600 dark:text-sky-400" });
              $$renderer4.push(`<!----> Internal Procedure`);
            }
            $$renderer4.push(`<!--]--></span> <span class="text-muted-foreground text-xs font-medium">${escape_html(owningOffice())} Office</span></div> <h1 class="text-foreground text-xl font-bold tracking-tight sm:text-2xl">${escape_html(source?.title ?? "Untitled Source")}</h1> <p class="text-muted-foreground text-xs">${escape_html(status.desc)}</p></div> <div class="flex items-center gap-3"><span${attr_class(`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold ${status.badgeClass}`)}>`);
            if (status.icon) {
              $$renderer4.push("<!--[-->");
              status.icon($$renderer4, { class: "size-3.5" });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` ${escape_html(status.label)}</span> <span class="bg-muted/60 border-border/60 text-muted-foreground rounded border px-2 py-1 font-mono text-xs">v${escape_html(version.version)}</span></div></div> <div class="border-border/60 border-t pt-4"><p class="text-muted-foreground mb-3 font-mono text-[11px] font-bold tracking-wider uppercase">Governance Lifecycle Pipeline</p> <div class="grid grid-cols-2 gap-2.5 text-xs sm:gap-3 md:grid-cols-4"><div class="flex items-center gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-emerald-700 dark:text-emerald-300"><div class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">`);
            Check($$renderer4, { class: "size-3.5 text-emerald-600 dark:text-emerald-400" });
            $$renderer4.push(`<!----></div> <div class="min-w-0"><p class="text-foreground font-semibold">1. Draft Created</p> <p class="text-muted-foreground truncate text-[10px]">Authoring complete</p></div></div> <div${attr_class(`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep() >= 2 ? "border-emerald-500/20 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300" : "bg-muted/30 border-border/60 text-muted-foreground"}`)}><div${attr_class(`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep() >= 2 ? "bg-emerald-500/20 text-emerald-600 dark:text-emerald-400" : "bg-muted text-muted-foreground"}`)}>`);
            if (currentStep() > 2) {
              $$renderer4.push("<!--[0-->");
              Check($$renderer4, { class: "size-3.5" });
            } else if (currentStep() === 2) {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`<span class="size-2 animate-ping rounded-full bg-amber-500"></span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<span class="font-mono text-[10px]">2</span>`);
            }
            $$renderer4.push(`<!--]--></div> <div class="min-w-0"><p class="text-foreground font-semibold">2. Extraction &amp; Digest</p> <p class="text-muted-foreground truncate text-[10px]">Digest fixture sealed</p></div></div> <div${attr_class(`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep() >= 3 ? "border-emerald-500/20 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300" : currentStep() === 2 ? "border-amber-500/30 bg-amber-500/5" : "bg-muted/30 border-border/60 text-muted-foreground"}`)}><div${attr_class(`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep() >= 3 ? "bg-emerald-500/20 text-emerald-600 dark:text-emerald-400" : currentStep() === 2 ? "bg-amber-500/20 text-amber-600" : "bg-muted text-muted-foreground"}`)}>`);
            if (currentStep() >= 3) {
              $$renderer4.push("<!--[0-->");
              Check($$renderer4, { class: "size-3.5" });
            } else if (currentStep() === 2) {
              $$renderer4.push("<!--[1-->");
              Clock($$renderer4, { class: "size-3.5" });
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<span class="font-mono text-[10px]">3</span>`);
            }
            $$renderer4.push(`<!--]--></div> <div class="min-w-0"><p class="text-foreground font-semibold">3. Office Review</p> <p class="text-muted-foreground truncate text-[10px]">${escape_html(isCrossOffice() ? `${signedJointOfficeCount()}/2 Signatures` : "Supervisor signoff")}</p></div></div> <div${attr_class(`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep() >= 4 ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-muted/30 border-border/60 text-muted-foreground"}`)}><div${attr_class(`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep() >= 4 ? "bg-emerald-500/20 text-emerald-600 dark:text-emerald-400" : "bg-muted text-muted-foreground"}`)}>`);
            if (currentStep() >= 4) {
              $$renderer4.push("<!--[0-->");
              Sparkles($$renderer4, { class: "size-3.5" });
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<span class="font-mono text-[10px]">4</span>`);
            }
            $$renderer4.push(`<!--]--></div> <div class="min-w-0"><p class="text-foreground font-semibold">4. Active Corpus</p> <p class="text-muted-foreground truncate text-[10px]">Live in Xiam RAG</p></div></div></div></div></section> <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12"><div class="space-y-6 lg:col-span-8">`);
            if (isFaq()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border/80 bg-card space-y-5 rounded-xl border p-5 shadow-xs sm:p-6"><div class="space-y-2"><div class="flex items-center gap-2"><div class="flex size-6 items-center justify-center rounded-md bg-purple-500/10 text-xs font-bold text-purple-600 dark:text-purple-400">Q</div> <h3 class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Applicant Question</h3></div> <p class="text-foreground pl-8 text-base leading-snug font-bold sm:text-lg">${escape_html(version.question || "No question text recorded.")}</p></div> <div class="bg-border/60 h-px"></div> <div class="space-y-2"><div class="flex items-center gap-2"><div class="flex size-6 items-center justify-center rounded-md bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:text-emerald-400">A</div> <h3 class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Official Answer</h3></div> <div class="prose prose-sm dark:prose-invert text-foreground/90 max-w-none pl-8 leading-relaxed whitespace-pre-wrap">${escape_html(version.answer || "No official answer recorded.")}</div></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs sm:p-6"><div class="flex items-center justify-between"><div class="flex items-center gap-2">`);
              Book_open($$renderer4, { class: "size-4.5 text-sky-600 dark:text-sky-400" });
              $$renderer4.push(`<!----> <h3 class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Governing Policy Content</h3></div> <span class="text-muted-foreground text-xs">Canonical Text</span></div> <div class="bg-muted/20 border-border/60 text-foreground rounded-lg border p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap sm:p-5">${escape_html(version.body_text || version.extraction_text || "No policy text available.")}</div></div>`);
            }
            $$renderer4.push(`<!--]--> <div class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"><div class="text-foreground flex items-center gap-2 text-sm font-semibold">`);
            Bot($$renderer4, { class: "text-primary size-4.5" });
            $$renderer4.push(`<!----> <span>Xiam Grounding &amp; Retrieval Contract</span></div> <p class="text-muted-foreground text-xs leading-relaxed">`);
            if (source.audience === "staff_and_applicant_inquiry") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`When published and active, this source is
                                indexed for authenticated applicant inquiry and
                                staff portals. It is strictly cited through
                                groundings and evidence bundles.`);
            } else if (source.audience === "cross_office_staff") {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`Restricted to authorized staff in Registrar and
                                Guidance offices. It requires dual-office head
                                signatures before any AI retrieval can reference
                                it.`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`Internal to the ${escape_html(owningOffice())} office only. General
                                staff outside this office and applicants cannot retrieve
                                this content.`);
            }
            $$renderer4.push(`<!--]--></p></div> `);
            if ((version.fixtures ?? []).length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"><div class="flex items-center justify-between"><h3 class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Verification Probes (${escape_html(version.fixtures.length)})</h3> <span class="text-muted-foreground text-[11px]">Sealed digest test vectors</span></div> <div class="border-border/60 overflow-x-auto rounded-lg border">`);
              if (Table) {
                $$renderer4.push("<!--[-->");
                Table($$renderer4, {
                  class: "w-full text-xs",
                  children: ($$renderer5) => {
                    if (Table_header) {
                      $$renderer5.push("<!--[-->");
                      Table_header($$renderer5, {
                        class: "bg-muted/40",
                        children: ($$renderer6) => {
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    class: "px-3 py-2",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Kind`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    class: "px-3 py-2",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Probe Query`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    class: "px-3 py-2",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Expected Outcome`);
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
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Table_body) {
                      $$renderer5.push("<!--[-->");
                      Table_body($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!--[-->`);
                          const each_array = ensure_array_like(version.fixtures);
                          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                            let fix = each_array[$$index];
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "px-3 py-2 font-mono font-semibold",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(fix.kind)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "px-3 py-2",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(fix.query_text)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "text-muted-foreground px-3 py-2",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(fix.expected_outcome)}`);
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
              $$renderer4.push(`</div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (capabilities.approve && ["active", "approved", "indexing"].includes(version.status)) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-3 rounded-xl border border-rose-500/30 bg-rose-500/[0.03] p-5"><h3 class="flex items-center gap-1.5 font-mono text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400">`);
              Ban($$renderer4, { class: "size-4" });
              $$renderer4.push(`<!----> Revocation Controls</h3> <p class="text-muted-foreground text-xs">Revoking an active source immediately expunges
                                it from all local lexical and vector RAG
                                indices.</p> <div class="flex flex-col gap-2 sm:flex-row">`);
              Input($$renderer4, {
                class: "min-h-9 text-xs",
                placeholder: "Reason for revocation (e.g. replaced by newer resolution)",
                get value() {
                  return revokeReason;
                },
                set value($$value) {
                  revokeReason = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              Button($$renderer4, {
                variant: "destructive",
                size: "sm",
                class: "min-h-9 shrink-0 gap-1.5 text-xs font-medium",
                onclick: revoke,
                children: ($$renderer5) => {
                  Ban($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----> Revoke Version`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-5 lg:col-span-4"><div class="border-border/80 bg-card space-y-4 rounded-xl border p-5 shadow-xs"><div class="text-foreground flex items-center gap-2 text-sm font-semibold">`);
            Key($$renderer4, { class: "text-primary size-4" });
            $$renderer4.push(`<!----> <span>Cryptographic Provenance</span></div> <div class="space-y-3 text-xs"><div><span class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase">Publication Digest</span> `);
            if (version.publication_digest) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="bg-muted/40 border-border/60 mt-1 flex items-center justify-between rounded-lg border p-2 font-mono text-[11px]"><span class="max-w-[200px] truncate"${attr("title", version.publication_digest)}>${escape_html(version.publication_digest.slice(0, 10))}...${escape_html(version.publication_digest.slice(-10))}</span> <button type="button" class="text-muted-foreground hover:text-foreground p-1" title="Copy full digest">`);
              {
                $$renderer4.push("<!--[-1-->");
                Copy($$renderer4, { class: "size-3.5" });
              }
              $$renderer4.push(`<!--]--></button></div> <div class="mt-1 flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">`);
              Shield_check($$renderer4, { class: "size-3" });
              $$renderer4.push(`<!----> Verified SHA-256 Digest</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="text-muted-foreground mt-0.5 text-xs italic">Pending signoff and sealing</p>`);
            }
            $$renderer4.push(`<!--]--></div> `);
            if (version.extraction_digest) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div><span class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase">Extraction Digest</span> <p class="text-muted-foreground bg-muted/30 mt-0.5 truncate rounded px-2 py-1 font-mono text-[10px]">${escape_html(version.extraction_digest)}</p></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div><span class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase">Version Identifier</span> <p class="text-muted-foreground bg-muted/30 mt-0.5 truncate rounded px-2 py-1 font-mono text-[10px]">${escape_html(version.id)}</p></div></div></div> `);
            if (isCrossOffice()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-3 rounded-xl border border-purple-500/30 bg-purple-500/[0.03] p-5 shadow-xs"><div class="flex items-center justify-between"><span class="flex items-center gap-1.5 font-mono text-xs font-bold tracking-wider text-purple-700 uppercase dark:text-purple-300">`);
              Users($$renderer4, { class: "size-3.5" });
              $$renderer4.push(`<!----> Joint Office Signatures</span> <span class="font-mono text-[11px] font-bold text-purple-700 dark:text-purple-300">${escape_html(signedJointOfficeCount())} of 2</span></div> <p class="text-muted-foreground text-xs">Both office heads must sign this exact version
                                digest before publication is unlocked.</p> <div class="space-y-2"><!--[-->`);
              const each_array_1 = ensure_array_like(requiredJointOffices);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let office = each_array_1[$$index_1];
                const approval = approvalForOffice(office.key);
                $$renderer4.push(`<div${attr_class(`space-y-1 rounded-lg border p-3 text-xs ${approval ? "border-emerald-500/20 bg-emerald-500/5" : "bg-muted/40 border-border/60"}`)}><div class="flex items-center justify-between"><span class="text-foreground font-semibold">${escape_html(office.label)}</span> `);
                if (approval) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">`);
                  Circle_check($$renderer4, { class: "size-3" });
                  $$renderer4.push(`<!----> Signed</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<span class="text-muted-foreground text-[10px] italic">Awaiting Signature</span>`);
                }
                $$renderer4.push(`<!--]--></div> `);
                if (approval) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-muted-foreground text-[11px]">Approved: ${escape_html(formatTimestamp(approval.approved_at))}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              }
              $$renderer4.push(`<!--]--></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"><h3 class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase">Audit &amp; Governance Trail</h3> <dl class="space-y-3 text-xs"><div class="border-border/40 flex items-center justify-between border-b pb-2"><dt class="text-muted-foreground">Created</dt> <dd class="text-foreground font-medium">${escape_html(formatTimestamp(version.created_at))}</dd></div> <div class="border-border/40 flex items-center justify-between border-b pb-2"><dt class="text-muted-foreground">Approved</dt> <dd class="text-foreground font-medium">${escape_html(formatTimestamp(version.approved_at))}</dd></div> <div class="border-border/40 flex items-center justify-between border-b pb-2"><dt class="text-muted-foreground">Published</dt> <dd class="text-foreground font-medium">${escape_html(formatTimestamp(version.published_at))}</dd></div> `);
            if (version.revoked_at) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex items-center justify-between text-rose-600 dark:text-rose-400"><dt>Revoked</dt> <dd class="font-medium">${escape_html(formatTimestamp(version.revoked_at))}</dd></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></dl></div></div></div>`);
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
  Show as default
};
