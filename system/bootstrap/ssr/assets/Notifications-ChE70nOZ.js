import { h as head, e as escape_html, a as ensure_array_like, b as attr, s as stringify, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { A as ActiveFilterChips } from "./ActiveFilterChips-BQD2-ACV.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { S as Sliders_vertical } from "./sliders-vertical-DiYN4y-t.js";
import { M as Mail } from "./mail-DBtkjaCt.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { B as Bell } from "./PreferencesMenu-CCPeta-5.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { U as Users } from "./users-DWlguTa_.js";
import { C as Calendar_days } from "./calendar-days-CnJjYUSZ.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
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
import "./AvatarBadge-DQ5Qru9A.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
function Notifications($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      master_controls = {
        outbound_email_enabled: true,
        portal_inbox_enabled: true,
        staff_action_alerts_enabled: true,
        can_manage: false,
        cycle_label: "Current Cycle"
      },
      domain_policies = {},
      activity = { data: [] },
      domain_cards = {},
      filters = {}
    } = $$props;
    ({ ...master_controls });
    const activityPage = derived(() => activity?.data ? activity : { data: [] });
    const rows = derived(() => activityPage().data ?? []);
    let search = filters.search ?? "";
    let status = filters.status ?? "";
    let domain = filters.domain ?? "";
    let channel = filters.channel ?? "";
    let from = filters.from ?? "";
    let to = filters.to ?? "";
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications" }
    ];
    const channelControls = [
      {
        key: "outbound_email_enabled",
        label: "Outbound Email (SMTP)",
        description: "Deliver status notices, permits, and official letters to applicant and staff email addresses.",
        icon: Mail
      },
      {
        key: "portal_inbox_enabled",
        label: "Applicant Portal Inbox",
        description: "Publish notices, exam permits, and scorecards directly into applicant portal accounts.",
        icon: Inbox
      },
      {
        key: "staff_action_alerts_enabled",
        label: "Staff Action Alerts",
        description: "Notify admissions evaluators and guidance staff of queued dossiers and room assignments.",
        icon: Bell
      }
    ];
    const cards = derived(() => [
      {
        key: "admissions",
        title: "Admissions Desk",
        office: "Registrar's Office",
        url: domain_policies.admissions?.url ?? domain_cards.admissions?.url ?? "/setup/notifications/admissions",
        canAccess: domain_cards.admissions?.can_access ?? domain_policies.admissions?.can_access ?? false,
        count: domain_cards.admissions?.count,
        icon: Users,
        policies: [
          {
            label: "Status Change Notices",
            active: domain_policies.admissions?.applicant_status_email_enabled ?? true
          },
          {
            label: "Registrar Arrival Alert",
            active: domain_policies.admissions?.registrar_new_submission_alert_enabled ?? true
          },
          {
            label: "Official Offer Letter PDF",
            attached: domain_policies.admissions?.attach_pdf_document ?? true
          }
        ],
        action: "Open Admissions Desk"
      },
      {
        key: "examination",
        title: "Examinations Desk",
        office: "Guidance & Testing Center",
        url: domain_policies.examination?.url ?? domain_cards.examination?.url ?? "/setup/notifications/examination",
        canAccess: domain_cards.examination?.can_access ?? domain_policies.examination?.can_access ?? false,
        count: domain_cards.examination?.count,
        icon: Calendar_days,
        policies: [
          {
            label: "Schedule Publication Notices",
            active: domain_policies.examination?.publication_email_enabled ?? true
          },
          {
            label: reminderSummary(domain_policies.examination?.reminder_offsets_minutes),
            active: domain_policies.examination?.reminder_email_enabled ?? true
          },
          {
            label: "Examination Permit PDF",
            attached: domain_policies.examination?.attach_pdf_document ?? true
          }
        ],
        action: "Open Examination Desk"
      },
      {
        key: "results",
        title: "Results & Counseling Desk",
        office: "Guidance Center",
        url: domain_policies.results?.url ?? domain_cards.results?.url ?? "/setup/notifications/results",
        canAccess: domain_cards.results?.can_access ?? domain_policies.results?.can_access ?? false,
        count: domain_cards.results?.count,
        icon: File_text,
        policies: [
          {
            label: "Portal Score Release",
            active: domain_policies.results?.candidate_portal_release_enabled ?? true
          },
          { label: "Consultation Notices", governed: true },
          {
            label: "Official Scorecard PDF",
            attached: domain_policies.results?.attach_pdf_document ?? true
          }
        ],
        action: "Open Results Desk"
      }
    ]);
    const activeFilterChips = derived(() => [
      ...search ? [{ id: "search", label: `Search: ${search}` }] : [],
      ...status ? [{ id: "status", label: `Status: ${statusLabel(status)}` }] : [],
      ...domain ? [{ id: "domain", label: `Domain: ${domainLabel(domain)}` }] : [],
      ...channel ? [
        { id: "channel", label: `Channel: ${channelLabel(channel)}` }
      ] : [],
      ...from ? [{ id: "from", label: `From: ${from}` }] : [],
      ...to ? [{ id: "to", label: `To: ${to}` }] : []
    ]);
    function reminderSummary(offsets) {
      if (!Array.isArray(offsets)) return "Countdown Reminders (48h/24h)";
      const has48Hours = offsets.includes(2880);
      const has24Hours = offsets.includes(1440);
      if (has48Hours && has24Hours) return "Countdown Reminders (48h/24h)";
      if (has48Hours) return "Countdown Reminder (48h)";
      if (has24Hours) return "Countdown Reminder (24h)";
      return "Countdown Reminders";
    }
    function statusLabel(value) {
      return {
        pending: "Queued",
        queued: "Queued",
        processing: "Queued",
        in_progress: "Queued",
        retrying: "Queued",
        retry_scheduled: "Queued",
        delivered: "Delivered",
        accepted_mail: "Delivered",
        available_in_app: "Delivered",
        failed: "Bounced / Failed",
        failed_terminal: "Bounced / Failed",
        outcome_unknown: "Bounced / Failed",
        superseded: "Outdated",
        unknown: "Bounced / Failed",
        mixed: "Queued"
      }[value] ?? value;
    }
    function domainLabel(value) {
      return {
        admissions: "Admissions",
        examination: "Examinations",
        results: "Results"
      }[value] ?? value;
    }
    function channelLabel(value) {
      return { in_app: "Portal Inbox", mail: "Email" }[value] ?? value;
    }
    function applyFilters() {
      router.get(
        "/setup/notifications",
        {
          search: search || void 0,
          status: status || void 0,
          domain: domain || void 0,
          channel: channel || void 0,
          from: from || void 0,
          to: to || void 0
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }
    function clearFilter(id) {
      if (id === "search") search = "";
      if (id === "status") status = "";
      if (id === "domain") domain = "";
      if (id === "channel") channel = "";
      if (id === "from") from = "";
      if (id === "to") to = "";
      applyFilters();
    }
    function clearAllFilters() {
      search = "";
      status = "";
      domain = "";
      channel = "";
      from = "";
      to = "";
      applyFilters();
    }
    function occurrenceHref(row) {
      return `/setup/notifications/occurrences/${encodeURIComponent(row.occurrence_id)}`;
    }
    function statusTone(value) {
      if (["delivered", "accepted_mail", "available_in_app"].includes(value)) {
        return "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300";
      }
      if (["failed", "failed_terminal", "outcome_unknown", "unknown"].includes(value)) {
        return "border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300";
      }
      if (["superseded"].includes(value)) {
        return "border-muted-foreground/20 bg-muted text-muted-foreground";
      }
      return "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300";
    }
    function occurredAt(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }
    head("13gi9g3", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Email &amp; Notifications · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 pb-40 sm:p-6 sm:pb-40">`);
        PageHeaderBar($$renderer3, {
          title: "Email & Notifications",
          description: "System delivery channels, office notice policies, and recent dispatch activity."
        });
        $$renderer3.push(`<!----> <section aria-labelledby="delivery-channels-heading" class="space-y-4">`);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "overflow-hidden border-border/70 bg-card shadow-xs",
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  class: "border-b border-border/60 bg-muted/20 p-5 sm:p-6",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="flex flex-wrap items-start justify-between gap-4"><div class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">`);
                    Sliders_vertical($$renderer5, { class: "size-5" });
                    $$renderer5.push(`<!----></span> <div class="space-y-1">`);
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        id: "delivery-channels-heading",
                        class: "text-lg",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Delivery Channels`);
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
                          $$renderer6.push(`<!---->System channels active for ${escape_html(master_controls.cycle_label ?? "the active academic cycle")}.`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(`</div></div> `);
                    Badge($$renderer5, {
                      variant: "outline",
                      class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->All Channels Operational`);
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
                  class: "grid divide-y divide-border/60 p-0 md:grid-cols-3 md:divide-x md:divide-y-0",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!--[-->`);
                    const each_array = ensure_array_like(channelControls);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let item = each_array[$$index];
                      $$renderer5.push(`<div class="flex min-h-28 items-start gap-3.5 p-5 sm:p-6"><span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      if (item.icon) {
                        $$renderer5.push("<!--[-->");
                        item.icon($$renderer5, { class: "size-4" });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(`</span> <div class="min-w-0 flex-1 space-y-1.5"><div class="flex items-center justify-between gap-2"><p class="text-sm font-semibold text-foreground">${escape_html(item.label)}</p> <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"><span class="size-1.5 rounded-full bg-emerald-500"></span> Active</span></div> <p class="text-xs leading-relaxed text-muted-foreground">${escape_html(item.description)}</p></div></div>`);
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
        $$renderer3.push(`</section> <section aria-labelledby="operational-desks-heading" class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-2"><div class="space-y-1"><h2 id="operational-desks-heading" class="text-lg font-semibold tracking-tight text-foreground">Operational Desks</h2> <p class="text-sm text-muted-foreground">Notice templates, countdown schedules, and document attachments managed by each office.</p></div></div> <div class="grid grid-cols-1 gap-4 lg:grid-cols-3"><!--[-->`);
        const each_array_1 = ensure_array_like(cards());
        for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
          let card = each_array_1[$$index_2];
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "flex h-full flex-col overflow-hidden border-border/70 bg-card shadow-sm",
              children: ($$renderer4) => {
                if (Card_header) {
                  $$renderer4.push("<!--[-->");
                  Card_header($$renderer4, {
                    class: "border-b border-border/60 pb-4",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex items-start justify-between gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">`);
                      if (card.icon) {
                        $$renderer5.push("<!--[-->");
                        card.icon($$renderer5, { class: "size-5" });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(`</span> `);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "max-w-[75%] text-right text-[10px] leading-relaxed",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(card.office)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div> `);
                      if (Card_title) {
                        $$renderer5.push("<!--[-->");
                        Card_title($$renderer5, {
                          class: "pt-2 text-base",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(card.title)}`);
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
                    class: "flex flex-1 flex-col gap-4 pt-4",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="space-y-3"><!--[-->`);
                      const each_array_2 = ensure_array_like(card.policies);
                      for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                        let policy = each_array_2[$$index_1];
                        $$renderer5.push(`<div class="flex items-center justify-between gap-3 text-sm"><span class="text-muted-foreground">${escape_html(policy.label)}</span> `);
                        if (policy.governed) {
                          $$renderer5.push("<!--[0-->");
                          Badge($$renderer5, {
                            variant: "outline",
                            class: "shrink-0 border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Workflow Governed`);
                            },
                            $$slots: { default: true }
                          });
                        } else if (policy.attached) {
                          $$renderer5.push("<!--[1-->");
                          $$renderer5.push(`<span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">`);
                          Circle_check($$renderer5, { class: "size-3.5" });
                          $$renderer5.push(`<!---->Attached</span>`);
                        } else if (policy.active) {
                          $$renderer5.push("<!--[2-->");
                          $$renderer5.push(`<span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">`);
                          Circle_check($$renderer5, { class: "size-3.5" });
                          $$renderer5.push(`<!---->Active</span>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                          $$renderer5.push(`<span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-muted-foreground">`);
                          Circle_alert($$renderer5, { class: "size-3.5" });
                          $$renderer5.push(`<!---->Paused</span>`);
                        }
                        $$renderer5.push(`<!--]--></div>`);
                      }
                      $$renderer5.push(`<!--]--></div> `);
                      if (card.canAccess && card.count !== void 0) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="mt-auto rounded-lg border border-border/60 bg-muted/30 p-3"><p class="text-xs text-muted-foreground">Scoped notification occurrences</p> <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">${escape_html(card.count)}</p></div>`);
                      } else if (!card.canAccess) {
                        $$renderer5.push("<!--[1-->");
                        $$renderer5.push(`<p class="mt-auto rounded-lg bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground">Operational activity is available only within authorized scope.</p>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> <div class="border-t border-border/60 pt-4">`);
                      if (card.canAccess) {
                        $$renderer5.push("<!--[0-->");
                        Link($$renderer5, {
                          href: card.url,
                          class: "inline-flex min-h-10 w-full items-center justify-between rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(card.action)}`);
                            Arrow_right($$renderer6, { class: "size-4" });
                            $$renderer6.push(`<!---->`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        $$renderer5.push(`<p class="text-center text-xs text-muted-foreground">This office desk is outside your current scope.</p>`);
                      }
                      $$renderer5.push(`<!--]--></div>`);
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
        $$renderer3.push(`<!--]--></div></section> <section aria-labelledby="activity-heading" class="space-y-4"><div class="space-y-1"><h2 id="activity-heading" class="text-lg font-semibold tracking-tight text-foreground">Recent Dispatch Activity</h2> <p class="text-sm text-muted-foreground">Record of recent candidate and staff notifications across all offices.</p></div> <div class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 shadow-xs sm:grid-cols-2 xl:grid-cols-6"><label class="grid gap-1.5 text-xs font-medium text-muted-foreground sm:col-span-2" for="activity-search">Search notifications <input id="activity-search" type="search"${attr("value", search)} placeholder="Search safe notification summaries" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground"/></label> <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-domain">Domain `);
        $$renderer3.select(
          {
            id: "activity-domain",
            value: domain,
            class: "h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`All authorized domains`);
            });
            $$renderer4.option({ value: "admissions" }, ($$renderer5) => {
              $$renderer5.push(`Admissions`);
            });
            $$renderer4.option({ value: "examination" }, ($$renderer5) => {
              $$renderer5.push(`Examinations`);
            });
            $$renderer4.option({ value: "results" }, ($$renderer5) => {
              $$renderer5.push(`Results`);
            });
          }
        );
        $$renderer3.push(`</label> <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-status">Status `);
        $$renderer3.select(
          {
            id: "activity-status",
            value: status,
            class: "h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`All statuses`);
            });
            $$renderer4.option({ value: "pending" }, ($$renderer5) => {
              $$renderer5.push(`Queued`);
            });
            $$renderer4.option({ value: "processing" }, ($$renderer5) => {
              $$renderer5.push(`Queued`);
            });
            $$renderer4.option({ value: "delivered" }, ($$renderer5) => {
              $$renderer5.push(`Delivered`);
            });
            $$renderer4.option({ value: "failed_terminal" }, ($$renderer5) => {
              $$renderer5.push(`Bounced / Failed`);
            });
            $$renderer4.option({ value: "outcome_unknown" }, ($$renderer5) => {
              $$renderer5.push(`Bounced / Failed`);
            });
            $$renderer4.option({ value: "superseded" }, ($$renderer5) => {
              $$renderer5.push(`Outdated`);
            });
          }
        );
        $$renderer3.push(`</label> <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-channel">Channel `);
        $$renderer3.select(
          {
            id: "activity-channel",
            value: channel,
            class: "h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`All channels`);
            });
            $$renderer4.option({ value: "mail" }, ($$renderer5) => {
              $$renderer5.push(`Email`);
            });
            $$renderer4.option({ value: "in_app" }, ($$renderer5) => {
              $$renderer5.push(`Portal Inbox`);
            });
          }
        );
        $$renderer3.push(`</label> <div class="grid grid-cols-2 gap-2 sm:col-span-2 xl:col-span-1"><label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-from">From<input id="activity-from" type="date"${attr("value", from)} class="h-10 min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground"/></label> <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-to">To<input id="activity-to" type="date"${attr("value", to)} class="h-10 min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground"/></label></div></div> `);
        ActiveFilterChips($$renderer3, {
          chips: activeFilterChips(),
          onRemove: clearFilter,
          onClearAll: clearAllFilters
        });
        $$renderer3.push(`<!----> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "overflow-hidden border-border/70 bg-card shadow-sm",
            children: ($$renderer4) => {
              if (rows().length > 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="overflow-x-auto">`);
                if (Table) {
                  $$renderer4.push("<!--[-->");
                  Table($$renderer4, {
                    class: "min-w-[900px]",
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
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Dispatched`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Office / Domain`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Notification`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Recipients`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Channels`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Status`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<span class="sr-only">Actions</span>`);
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
                            const each_array_3 = ensure_array_like(rows());
                            for (let $$index_4 = 0, $$length = each_array_3.length; $$index_4 < $$length; $$index_4++) {
                              let row = each_array_3[$$index_4];
                              if (Table_row) {
                                $$renderer6.push("<!--[-->");
                                Table_row($$renderer6, {
                                  children: ($$renderer7) => {
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "whitespace-nowrap text-xs text-muted-foreground",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(occurredAt(row.occurred_at))}`);
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
                                        class: "font-medium text-foreground",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(domainLabel(row.domain))}`);
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
                                        class: "max-w-sm",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<p class="font-medium text-foreground">${escape_html(row.safe_summary)}</p>`);
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
                                        class: "tabular-nums",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(row.recipient_count ?? "—")}`);
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
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<div class="flex flex-wrap gap-1"><!--[-->`);
                                          const each_array_4 = ensure_array_like(row.channel_summaries ?? []);
                                          for (let $$index_3 = 0, $$length2 = each_array_4.length; $$index_3 < $$length2; $$index_3++) {
                                            let item = each_array_4[$$index_3];
                                            Badge($$renderer8, {
                                              variant: "outline",
                                              class: "border-border/70 bg-muted/30",
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->${escape_html(channelLabel(item.channel))}${escape_html(item.count != null ? ` · ${item.count}` : "")}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          }
                                          $$renderer8.push(`<!--]--></div>`);
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
                                        children: ($$renderer8) => {
                                          Badge($$renderer8, {
                                            variant: "outline",
                                            class: `whitespace-nowrap ${stringify(statusTone(row.status))}`,
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->${escape_html(statusLabel(row.status))}`);
                                            },
                                            $$slots: { default: true }
                                          });
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
                                        children: ($$renderer8) => {
                                          Link($$renderer8, {
                                            href: row.detail_url ?? occurrenceHref(row),
                                            class: "inline-flex min-h-8 items-center justify-center rounded-md border border-border/80 bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:border-primary/50 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->View Details`);
                                            },
                                            $$slots: { default: true }
                                          });
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
                $$renderer4.push(`</div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "flex min-h-44 flex-col items-center justify-center gap-2 p-6 text-center",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<span class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">`);
                      Mail($$renderer5, { class: "size-5" });
                      $$renderer5.push(`<!----></span> <p class="font-medium text-foreground">No recent dispatch activity found.</p> <p class="max-w-md text-sm text-muted-foreground">Try another search or filter, or check back when scoped activity is available.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` `);
        SimplePagination($$renderer3, { data: activityPage() });
        $$renderer3.push(`<!----></section></div>`);
      }
    });
  });
}
export {
  Notifications as default
};
