import { h as head, e as escape_html, c as attr_class, a as ensure_array_like, B as Button, A as clsx, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import "clsx";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { X } from "./x-CnxrMbMn.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { B as Ban } from "./ban-CUQ_jw2o.js";
import { B as Bot } from "./bot-LOOyhTeB.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { G as Globe } from "./globe-BeSTb5EF.js";
import { U as Users } from "./users-DWlguTa_.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./dialog-description-D12L0QA9.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./circle-check-C59ZS7Zv.js";
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
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./check-DK45Y9SJ.js";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      sources = [],
      filters = {},
      statuses = [],
      capabilities = { manage: false, approve: false },
      current_office = null,
      message = null
    } = $$props;
    let viewMode = "responsive";
    let activeTab = "all";
    let clientSearch = filters.search ?? "";
    let filterOffice = "all";
    let filterStatus = filters.status ?? "";
    let expandedCardId = null;
    let debounceTimer;
    const totalCount = derived(() => sources.length);
    const activeCount = derived(() => sources.filter((s) => s.latest_version?.status === "active").length);
    const reviewCount = derived(() => sources.filter((s) => s.latest_version?.status === "review_required").length);
    const policyCount = derived(() => sources.filter((s) => s.source_class === "institution_policy").length);
    const faqCount = derived(() => sources.filter((s) => s.source_class === "public_faq").length);
    const knowledgeContext = derived(() => {
      const safetyKey = `${page_state}:${totalCount()}:${activeCount()}:${reviewCount()}:${capabilities?.manage ? "m" : "nom"}:${capabilities?.approve ? "a" : "noa"}`;
      return {
        entityKey: "xiam-knowledge-hub",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        totalCount: totalCount(),
        activeCount: activeCount(),
        reviewCount: reviewCount(),
        policyCount: policyCount(),
        faqCount: faqCount(),
        canManage: Boolean(capabilities?.manage),
        canApprove: Boolean(capabilities?.approve)
      };
    });
    const filteredSources = derived(() => {
      let list = sources;
      if (activeTab === "policies") {
        list = list.filter((s) => s.source_class === "institution_policy");
      } else if (activeTab === "faqs") {
        list = list.filter((s) => s.source_class === "public_faq");
      } else if (activeTab === "review") {
        list = list.filter((s) => s.latest_version?.status === "review_required");
      }
      if (filterOffice && filterOffice !== "all") {
        list = list.filter((s) => s.owning_office === filterOffice);
      }
      if (filterStatus && filterStatus !== "all" && filterStatus !== "") {
        list = list.filter((s) => s.latest_version?.status === filterStatus);
      }
      if (clientSearch.trim()) {
        const q = clientSearch.trim().toLowerCase();
        list = list.filter((s) => {
          const title = (s.title ?? "").toLowerCase();
          const key = (s.key ?? "").toLowerCase();
          const question = (s.latest_version?.question ?? "").toLowerCase();
          const answer = (s.latest_version?.answer ?? "").toLowerCase();
          const body = (s.latest_version?.body_text ?? "").toLowerCase();
          return title.includes(q) || key.includes(q) || question.includes(q) || answer.includes(q) || body.includes(q);
        });
      }
      return list;
    });
    function applyServerFilters() {
      router.get(
        "/knowledge",
        {
          search: clientSearch || void 0,
          status: filterStatus || void 0
        },
        { preserveState: true, replace: true }
      );
    }
    function onSearchInput() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(applyServerFilters, 400);
    }
    function resetAllFilters() {
      clientSearch = "";
      filterOffice = "all";
      filterStatus = "";
      activeTab = "all";
      applyServerFilters();
    }
    function toggleCardExpand(id) {
      expandedCardId = expandedCardId === id ? null : id;
    }
    function statusConfig(status) {
      switch (status) {
        case "active":
          return {
            label: "Active Source",
            badgeClass: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20",
            dotClass: "bg-emerald-500",
            icon: Sparkles
          };
        case "review_required":
          return {
            label: "Needs Review",
            badgeClass: "bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20",
            dotClass: "bg-amber-500",
            icon: Clock
          };
        case "approved":
          return {
            label: "Approved",
            badgeClass: "bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20",
            dotClass: "bg-sky-500",
            icon: Shield_check
          };
        case "draft":
          return {
            label: "Draft",
            badgeClass: "bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/20",
            dotClass: "bg-slate-400",
            icon: File_text
          };
        case "extracting":
        case "indexing":
          return {
            label: status === "indexing" ? "Indexing..." : "Extracting...",
            badgeClass: "bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20 animate-pulse",
            dotClass: "bg-purple-500",
            icon: Bot
          };
        case "revoked":
          return {
            label: "Revoked",
            badgeClass: "bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20",
            dotClass: "bg-rose-500",
            icon: Ban
          };
        case "index_failed":
        case "rejected":
          return {
            label: status === "index_failed" ? "Index Failed" : "Rejected",
            badgeClass: "bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20",
            dotClass: "bg-red-500",
            icon: Circle_alert
          };
        default:
          return {
            label: status ? status.replaceAll("_", " ") : "Draft",
            badgeClass: "bg-muted text-muted-foreground border-border",
            dotClass: "bg-muted-foreground",
            icon: File_text
          };
      }
    }
    function audienceBadge(audience) {
      if (audience === "staff_and_applicant_inquiry") {
        return {
          label: "Public & Applicants",
          icon: Globe,
          class: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20"
        };
      }
      if (audience === "cross_office_staff") {
        return {
          label: "Cross-Office",
          icon: Users,
          class: "bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20"
        };
      }
      return {
        label: "Office Internal",
        icon: Lock,
        class: "bg-muted/80 text-muted-foreground border-border/70"
      };
    }
    function officeLabel(office) {
      if (!office) return "Unassigned";
      if (office === "registrar") return "Registrar";
      if (office === "guidance") return "Guidance";
      if (typeof office === "object") return office.label ?? office.name ?? office.code ?? "Unassigned";
      return String(office);
    }
    function formatDate(isoString) {
      if (!isoString) return "—";
      try {
        const d = new Date(isoString);
        if (Number.isNaN(d.getTime())) return isoString;
        return new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric", year: "numeric" }).format(d);
      } catch {
        return isoString;
      }
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "FAQs & Internal Procedures" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1o402xe", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Xiam Knowledge &amp; Rules · Setup · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex items-center gap-2" data-tour="add-actions">`);
              if (capabilities.manage) {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: "/knowledge/create?type=policy",
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      variant: "outline",
                      class: "min-h-10 gap-2 font-medium",
                      children: ($$renderer7) => {
                        File_text($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Add Procedure`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Link($$renderer5, {
                  href: "/knowledge/create?type=faq",
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      class: "min-h-10 gap-2 font-medium shadow-xs",
                      children: ($$renderer7) => {
                        Plus($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Add FAQ`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!---->`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: "Internal Procedures & Staff FAQs",
              description: "Manage genuine internal procedures and explanatory FAQs. Canonical public policies belong to Communications → Public policies; intended audience does not enable applicant AI retrieval or admissions evaluation.",
              tourKey: "xiam-knowledge",
              context: knowledgeContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-6 text-center">`);
            Lock($$renderer4, { class: "text-destructive/80 mx-auto size-8" });
            $$renderer4.push(`<!----> <h3 class="text-foreground font-semibold">Access Restricted</h3> <p class="text-muted-foreground mx-auto max-w-md text-sm">You do not have permission to view or manage institutional
                    knowledge sources. Contact your system administrator for
                    guidance.</p></div>`);
          } else if (["failed", "error", "conflict"].includes(page_state)) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div role="alert" class="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-3 rounded-xl border px-4 py-3 text-sm">`);
            Circle_alert($$renderer4, { class: "mt-0.5 size-5 shrink-0" });
            $$renderer4.push(`<!----> <div><p class="font-medium">Knowledge catalog unavailable</p> <p class="text-destructive/80 mt-0.5 text-xs">${escape_html(message ?? "Failed to load policy sources. Please refresh or try again later.")}</p></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section data-tour="governance-pipeline" aria-label="Catalog Telemetry" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4"><div data-tour="active-telemetry" class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-emerald-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"><div class="flex items-start justify-between"><div><p class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase">Active Sources</p> <h3 class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl">${escape_html(activeCount())}</h3></div> <div class="relative flex size-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><span class="absolute -top-0.5 -right-0.5 flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span> <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span></span> `);
            Sparkles($$renderer4, { class: "size-4.5" });
            $$renderer4.push(`<!----></div></div> <p class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"><span class="inline-block size-1.5 rounded-full bg-emerald-500"></span> Indexed in local RAG</p></div> <div${attr_class(`relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] ${reviewCount() > 0 ? "border-amber-500/40 bg-amber-500/[0.03]" : "border-border/80 bg-card"}`)}><div class="flex items-start justify-between"><div><p class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase">Pending Review</p> <h3 class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl">${escape_html(reviewCount())}</h3></div> <div class="flex size-9 items-center justify-center rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400">`);
            Clock($$renderer4, { class: "size-4.5" });
            $$renderer4.push(`<!----></div></div> <p class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs">`);
            if (reviewCount() > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="inline-block size-1.5 rounded-full bg-amber-500"></span> <span class="font-medium text-amber-600 dark:text-amber-400">Action required by head</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<span class="bg-muted-foreground inline-block size-1.5 rounded-full"></span> All drafts cleared`);
            }
            $$renderer4.push(`<!--]--></p></div> <div class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-sky-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"><div class="flex items-start justify-between"><div><p class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase">Internal Procedures</p> <h3 class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl">${escape_html(policyCount())}</h3></div> <div class="flex size-9 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400">`);
            Book_open($$renderer4, { class: "size-4.5" });
            $$renderer4.push(`<!----></div></div> <p class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"><span class="inline-block size-1.5 rounded-full bg-sky-500"></span> Staff procedures, not public policies</p></div> <div class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-purple-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"><div class="flex items-start justify-between"><div><p class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase">Applicant FAQs</p> <h3 class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl">${escape_html(faqCount())}</h3></div> <div class="flex size-9 items-center justify-center rounded-lg border border-purple-500/20 bg-purple-500/10 text-purple-600 dark:text-purple-400">`);
            Circle_question_mark($$renderer4, { class: "size-4.5" });
            $$renderer4.push(`<!----></div></div> <p class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"><span class="inline-block size-1.5 rounded-full bg-purple-500"></span> Public inquiries</p></div></section> <div class="border-border/80 bg-card space-y-4 rounded-xl border p-4 shadow-xs"><div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"><div class="bg-muted/50 border-border/50 flex flex-wrap items-center gap-1.5 rounded-lg border p-1 text-sm"><button type="button"${attr_class(`rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === "all" ? "bg-background text-foreground font-semibold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>All Items (${escape_html(totalCount())})</button> <button type="button"${attr_class(`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === "policies" ? "bg-background text-foreground font-semibold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>`);
            File_text($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> Policies (${escape_html(policyCount())})</button> <button type="button"${attr_class(`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === "faqs" ? "bg-background text-foreground font-semibold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>`);
            Circle_question_mark($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> FAQs (${escape_html(faqCount())})</button> <button type="button"${attr_class(`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === "review" ? "bg-background text-foreground font-semibold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>`);
            Clock($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> Needs Review `);
            if (reviewCount() > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="py-0.2 inline-flex items-center justify-center rounded-full bg-amber-500 px-1.5 font-mono text-[10px] font-bold text-white">${escape_html(reviewCount())}</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></button></div> <div class="flex flex-wrap items-center gap-2.5"><div class="relative min-w-[220px] flex-1 sm:min-w-[260px] sm:flex-initial">`);
            Search($$renderer4, {
              class: "text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "knowledge-search",
              type: "search",
              placeholder: "Search title, Q&A, or text...",
              class: "min-h-9.5 pr-8 pl-9 text-xs sm:text-sm",
              oninput: onSearchInput,
              get value() {
                return clientSearch;
              },
              set value($$value) {
                clientSearch = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (clientSearch) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<button type="button" class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5" aria-label="Clear search">`);
              X($$renderer4, { class: "size-3.5" });
              $$renderer4.push(`<!----></button>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (Select) {
              $$renderer4.push("<!--[-->");
              Select($$renderer4, {
                type: "single",
                value: filterOffice,
                onValueChange: (v) => filterOffice = v ?? "all",
                children: ($$renderer5) => {
                  if (Select_trigger) {
                    $$renderer5.push("<!--[-->");
                    Select_trigger($$renderer5, {
                      class: "min-h-9.5 w-[140px] text-xs",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(filterOffice === "all" ? "All Offices" : officeLabel(filterOffice))}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Select_content) {
                    $$renderer5.push("<!--[-->");
                    Select_content($$renderer5, {
                      children: ($$renderer6) => {
                        if (Select_item) {
                          $$renderer6.push("<!--[-->");
                          Select_item($$renderer6, {
                            value: "all",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->All Offices`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Select_item) {
                          $$renderer6.push("<!--[-->");
                          Select_item($$renderer6, {
                            value: "registrar",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Registrar`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Select_item) {
                          $$renderer6.push("<!--[-->");
                          Select_item($$renderer6, {
                            value: "guidance",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Guidance`);
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
            if (Select) {
              $$renderer4.push("<!--[-->");
              Select($$renderer4, {
                type: "single",
                value: filterStatus,
                onValueChange: (v) => {
                  filterStatus = v ?? "";
                  applyServerFilters();
                },
                children: ($$renderer5) => {
                  if (Select_trigger) {
                    $$renderer5.push("<!--[-->");
                    Select_trigger($$renderer5, {
                      class: "min-h-9.5 w-[150px] text-xs",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(statuses.find((s) => s.value === filterStatus)?.label ?? "All Statuses")}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Select_content) {
                    $$renderer5.push("<!--[-->");
                    Select_content($$renderer5, {
                      children: ($$renderer6) => {
                        if (Select_item) {
                          $$renderer6.push("<!--[-->");
                          Select_item($$renderer6, {
                            value: "",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->All Statuses`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` <!--[-->`);
                        const each_array = ensure_array_like(statuses);
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let s = each_array[$$index];
                          if (Select_item) {
                            $$renderer6.push("<!--[-->");
                            Select_item($$renderer6, {
                              value: s.value,
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(s.label)}`);
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
            $$renderer4.push(`</div></div> `);
            if (clientSearch || filterOffice !== "all" || filterStatus !== "") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="text-muted-foreground border-border/50 flex items-center justify-between border-t pt-2 text-xs"><span>Showing <strong class="text-foreground">${escape_html(filteredSources().length)}</strong> of ${escape_html(totalCount())} items</span> <button type="button" class="text-primary inline-flex items-center gap-1 font-medium hover:underline">`);
              Rotate_ccw($$renderer4, { class: "size-3" });
              $$renderer4.push(`<!----> Reset filters</button></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (filteredSources().length === 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border/80 bg-card space-y-4 rounded-xl border border-dashed p-10 text-center"><div class="bg-muted/60 text-muted-foreground mx-auto flex size-12 items-center justify-center rounded-full">`);
              Book_open($$renderer4, { class: "size-6" });
              $$renderer4.push(`<!----></div> <div class="space-y-1"><h3 class="text-foreground text-base font-semibold">${escape_html(clientSearch || filterOffice !== "all" || filterStatus !== "" ? "No matching policies or FAQs found" : "No knowledge sources established yet")}</h3> <p class="text-muted-foreground mx-auto max-w-md text-xs sm:text-sm">${escape_html(clientSearch || filterOffice !== "all" || filterStatus !== "" ? "Try broadening your search term or resetting the office/status filters." : "Draft an internal staff procedure or explanatory FAQ for review. Canonical public policies are managed in Communications.")}</p></div> `);
              if (clientSearch || filterOffice !== "all" || filterStatus !== "") {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  onclick: resetAllFilters,
                  class: "gap-1.5 text-xs",
                  children: ($$renderer5) => {
                    Rotate_ccw($$renderer5, { class: "size-3.5" });
                    $$renderer5.push(`<!----> Clear all filters`);
                  },
                  $$slots: { default: true }
                });
              } else if (capabilities.manage) {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`<div class="flex items-center justify-center gap-3 pt-2">`);
                Link($$renderer4, {
                  href: "/knowledge/create?type=policy",
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "gap-1.5",
                      children: ($$renderer6) => {
                        File_text($$renderer6, { class: "size-4" });
                        $$renderer6.push(`<!----> Add First Procedure`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Link($$renderer4, {
                  href: "/knowledge/create?type=faq",
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      size: "sm",
                      class: "gap-1.5",
                      children: ($$renderer6) => {
                        Plus($$renderer6, { class: "size-4" });
                        $$renderer6.push(`<!----> Add First FAQ`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              {
                let table = function($$renderer5) {
                  $$renderer5.push(`<div class="border-border/80 bg-card overflow-hidden rounded-xl border shadow-xs">`);
                  if (Table) {
                    $$renderer5.push("<!--[-->");
                    Table($$renderer5, {
                      class: "w-full text-sm",
                      children: ($$renderer6) => {
                        if (Table_header) {
                          $$renderer6.push("<!--[-->");
                          Table_header($$renderer6, {
                            class: "bg-muted/40 text-xs",
                            children: ($$renderer7) => {
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_head) {
                                      $$renderer8.push("<!--[-->");
                                      Table_head($$renderer8, {
                                        class: "text-foreground px-4 py-3.5 font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Document / Question`);
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
                                        class: "text-foreground px-4 py-3.5 font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Type`);
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
                                        class: "text-foreground px-4 py-3.5 font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Scope &amp; Office`);
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
                                        class: "text-foreground px-4 py-3.5 font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Governance`);
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
                                        class: "text-foreground px-4 py-3.5 font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Updated`);
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
                                        class: "text-foreground px-4 py-3.5 text-right font-semibold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Actions`);
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
                              const each_array_1 = ensure_array_like(filteredSources());
                              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                                let row = each_array_1[$$index_1];
                                const status = statusConfig(row.latest_version?.status);
                                const aud = audienceBadge(row.audience);
                                const isFaq = row.source_class === "public_faq";
                                if (Table_row) {
                                  $$renderer7.push("<!--[-->");
                                  Table_row($$renderer7, {
                                    class: "hover:bg-muted/30 group transition-colors",
                                    children: ($$renderer8) => {
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "max-w-md px-4 py-3.5 align-top",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="space-y-1">`);
                                            Link($$renderer9, {
                                              href: `/knowledge/${row.id}`,
                                              class: "text-foreground hover:text-primary block font-medium transition-colors",
                                              children: ($$renderer10) => {
                                                if (isFaq && row.latest_version?.question) {
                                                  $$renderer10.push("<!--[0-->");
                                                  $$renderer10.push(`${escape_html(row.latest_version.question)}`);
                                                } else {
                                                  $$renderer10.push("<!--[-1-->");
                                                  $$renderer10.push(`${escape_html(row.title)}`);
                                                }
                                                $$renderer10.push(`<!--]-->`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push(`<!----> <div class="text-muted-foreground flex items-center gap-2 text-xs"><span class="bg-muted/60 border-border/60 rounded border px-1.5 py-0.5 font-mono text-[11px]">${escape_html(row.key)}</span></div> `);
                                            if (isFaq && row.latest_version?.answer) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`<p class="text-muted-foreground mt-1 line-clamp-1 text-xs">A: ${escape_html(row.latest_version.answer)}</p>`);
                                            } else if (row.latest_version?.body_text) {
                                              $$renderer9.push("<!--[1-->");
                                              $$renderer9.push(`<p class="text-muted-foreground mt-1 line-clamp-1 text-xs">${escape_html(row.latest_version.body_text)}</p>`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                            }
                                            $$renderer9.push(`<!--]--></div>`);
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
                                          class: "px-4 py-3.5 align-top whitespace-nowrap",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<span class="bg-muted/60 border-border/60 inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium">`);
                                            if (isFaq) {
                                              $$renderer9.push("<!--[0-->");
                                              Circle_question_mark($$renderer9, { class: "size-3.5 text-purple-600 dark:text-purple-400" });
                                              $$renderer9.push(`<!----> FAQ`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                              File_text($$renderer9, { class: "size-3.5 text-sky-600 dark:text-sky-400" });
                                              $$renderer9.push(`<!----> Policy`);
                                            }
                                            $$renderer9.push(`<!--]--></span>`);
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
                                          class: "px-4 py-3.5 align-top whitespace-nowrap",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="space-y-1"><div class="text-foreground flex items-center gap-1.5 text-xs font-medium"><span class="bg-primary size-1.5 rounded-full"></span> ${escape_html(officeLabel(row.owning_office))}</div> <span${attr_class(`inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] ${aud.class}`)}>`);
                                            if (aud.icon) {
                                              $$renderer9.push("<!--[-->");
                                              aud.icon($$renderer9, { class: "size-3" });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` ${escape_html(aud.label)}</span></div>`);
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
                                          class: "px-4 py-3.5 align-top whitespace-nowrap",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="space-y-1"><span${attr_class(`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${status.badgeClass}`)}>`);
                                            if (status.icon) {
                                              $$renderer9.push("<!--[-->");
                                              status.icon($$renderer9, { class: "size-3" });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` ${escape_html(status.label)}</span> <div class="text-muted-foreground font-mono text-[11px]">v${escape_html(row.latest_version?.version ?? 1)}</div></div>`);
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
                                          class: "text-muted-foreground px-4 py-3.5 align-top text-xs whitespace-nowrap",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(formatDate(row.latest_version?.updated_at ?? row.updated_at))}`);
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
                                          class: "px-4 py-3.5 text-right align-top whitespace-nowrap",
                                          children: ($$renderer9) => {
                                            Link($$renderer9, {
                                              href: `/knowledge/${row.id}`,
                                              children: ($$renderer10) => {
                                                Button($$renderer10, {
                                                  variant: "ghost",
                                                  size: "sm",
                                                  class: "hover:text-primary h-8 gap-1 text-xs",
                                                  children: ($$renderer11) => {
                                                    $$renderer11.push(`<span>Open</span> `);
                                                    Arrow_right($$renderer11, { class: "size-3.5" });
                                                    $$renderer11.push(`<!---->`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              },
                                              $$slots: { default: true }
                                            });
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
                }, cards = function($$renderer5) {
                  $$renderer5.push(`<ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
                  const each_array_2 = ensure_array_like(filteredSources());
                  for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                    let item = each_array_2[$$index_2];
                    const status = statusConfig(item.latest_version?.status);
                    const aud = audienceBadge(item.audience);
                    const isFaq = item.source_class === "public_faq";
                    const isExpanded = expandedCardId === item.id;
                    $$renderer5.push(`<li class="group border-border/80 bg-card hover:border-primary/40 flex flex-col justify-between rounded-xl border p-5 shadow-xs transition-all duration-200 hover:shadow-md dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"><div class="space-y-3.5"><div class="flex items-center justify-between gap-2"><div class="flex items-center gap-1.5"><span class="bg-muted/80 text-foreground border-border/60 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase">`);
                    if (isFaq) {
                      $$renderer5.push("<!--[0-->");
                      Circle_question_mark($$renderer5, { class: "size-3 text-purple-600 dark:text-purple-400" });
                      $$renderer5.push(`<!----> FAQ`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                      File_text($$renderer5, { class: "size-3 text-sky-600 dark:text-sky-400" });
                      $$renderer5.push(`<!----> Policy`);
                    }
                    $$renderer5.push(`<!--]--></span> <span class="text-muted-foreground text-xs font-medium">${escape_html(officeLabel(item.owning_office))}</span></div> <span${attr_class(`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold ${status.badgeClass}`)}>`);
                    if (status.icon) {
                      $$renderer5.push("<!--[-->");
                      status.icon($$renderer5, { class: "size-3" });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` ${escape_html(status.label)}</span></div> <div class="space-y-1">`);
                    Link($$renderer5, {
                      href: `/knowledge/${item.id}`,
                      class: "block",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<h4 class="text-foreground group-hover:text-primary text-sm leading-snug font-bold transition-colors">`);
                        if (isFaq && item.latest_version?.question) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`${escape_html(item.latest_version.question)}`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`${escape_html(item.title)}`);
                        }
                        $$renderer6.push(`<!--]--></h4>`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----> <div class="flex items-center gap-2"><span class="text-muted-foreground/80 bg-muted/40 rounded px-1.5 py-0.5 font-mono text-[10px]">${escape_html(item.key)}</span> <span${attr_class(`py-0.2 inline-flex items-center gap-1 rounded border px-1.5 text-[10px] ${aud.class}`)}>`);
                    if (aud.icon) {
                      $$renderer5.push("<!--[-->");
                      aud.icon($$renderer5, { class: "size-2.5" });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` ${escape_html(aud.label)}</span></div></div> `);
                    if (isFaq && item.latest_version?.answer) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="bg-muted/30 border-border/40 text-muted-foreground rounded-lg border p-2.5 text-xs"><p class="text-foreground/90 mb-1 text-[11px] font-semibold tracking-wider uppercase">Answer Preview</p> <p${attr_class(clsx(isExpanded ? "whitespace-pre-wrap" : "line-clamp-3 leading-relaxed"))}>${escape_html(item.latest_version.answer)}</p></div>`);
                    } else if (item.latest_version?.body_text) {
                      $$renderer5.push("<!--[1-->");
                      $$renderer5.push(`<div class="bg-muted/30 border-border/40 text-muted-foreground rounded-lg border p-2.5 text-xs"><p class="text-foreground/90 mb-1 text-[11px] font-semibold tracking-wider uppercase">Policy Scope</p> <p${attr_class(clsx(isExpanded ? "whitespace-pre-wrap" : "line-clamp-3 leading-relaxed"))}>${escape_html(item.latest_version.body_text)}</p></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div> <div class="border-border/60 mt-4 flex items-center justify-between border-t pt-3 text-xs"><div class="text-muted-foreground flex items-center gap-2 font-mono text-[11px]"><span>v${escape_html(item.latest_version?.version ?? 1)}</span> <span>•</span> <span>${escape_html(formatDate(item.latest_version?.updated_at ?? item.updated_at))}</span></div> <div class="flex items-center gap-1.5">`);
                    if (isFaq && item.latest_version?.answer?.length > 120 || !isFaq && item.latest_version?.body_text?.length > 120) {
                      $$renderer5.push("<!--[0-->");
                      Button($$renderer5, {
                        variant: "ghost",
                        size: "sm",
                        class: "text-muted-foreground hover:text-foreground h-7 px-2 text-[11px]",
                        onclick: () => toggleCardExpand(item.id),
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(isExpanded ? "Show less" : "Quick read")}`);
                        },
                        $$slots: { default: true }
                      });
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    Link($$renderer5, {
                      href: `/knowledge/${item.id}`,
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          size: "sm",
                          variant: "outline",
                          class: "group-hover:border-primary/50 h-7.5 gap-1 px-2.5 text-xs",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<span>Inspect</span> `);
                            Arrow_right($$renderer7, { class: "size-3" });
                            $$renderer7.push(`<!---->`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div></div></li>`);
                  }
                  $$renderer5.push(`<!--]--></ul>`);
                };
                SwitchableListView($$renderer4, {
                  get viewMode() {
                    return viewMode;
                  },
                  set viewMode($$value) {
                    viewMode = $$value;
                    $$settled = false;
                  },
                  table,
                  cards,
                  $$slots: { table: true, cards: true }
                });
              }
            }
            $$renderer4.push(`<!--]-->`);
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
  Index as default
};
