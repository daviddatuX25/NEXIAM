import { h as head, e as escape_html, b as attr, f as attr_style, a as ensure_array_like, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import "axios";
import "@inertiajs/core";
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
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
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
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      readiness = { overall_percent: 0, passed: 0, total: 0, groups: [] },
      capabilities = {
        setup: false,
        assessment: false,
        quota: false,
        knowledge: false,
        consultation_policy: false
      }
    } = $$props;
    let expanded = null;
    function categoryStatus(category) {
      const checks = category.checks ?? [];
      if (checks.some((check) => !check.passed && check.severity === "critical")) return "critical";
      if (checks.some((check) => !check.passed)) return "attention";
      return "ready";
    }
    function canConfigure(category) {
      if (category.key === "public_communications") {
        return capabilities.setup || capabilities.communications;
      }
      if (category.capability === "knowledge") return capabilities.knowledge;
      if (category.capability === "assessment") return capabilities.assessment;
      if (category.capability === "quota") return capabilities.quota;
      return capabilities.setup;
    }
    const allChecks = derived(() => (readiness.groups ?? []).flatMap((g) => (g.categories ?? []).flatMap((c) => c.checks ?? [])));
    const criticalBlockers = derived(() => allChecks().filter((c) => !c.passed && c.severity === "critical"));
    const firstFailingCat = derived(() => (readiness.groups ?? []).flatMap((g) => g.categories ?? []).find((cat) => (cat.checks ?? []).some((check) => !check.passed)) ?? null);
    const readinessContext = derived(() => {
      const pct = readiness.overall_percent ?? 0;
      const passed = readiness.passed ?? 0;
      const total = readiness.total ?? 0;
      const isReady = total > 0 && passed === total;
      const hasCrit = criticalBlockers().length > 0;
      const safetyKey = `${pct}:${passed}:${total}:${hasCrit ? "crit" : "ok"}:${firstFailingCat()?.key ?? "none"}`;
      return {
        entityKey: "setup-readiness",
        safetyKey,
        freshness: "current",
        overallPercent: pct,
        passedCount: passed,
        totalCount: total,
        isFullyReady: isReady,
        hasCriticalBlockers: hasCrit,
        criticalBlockerCount: criticalBlockers().length,
        firstFailingCategoryTitle: firstFailingCat()?.title ?? null,
        firstFailingCategoryDestination: firstFailingCat()?.destination ?? null,
        groupsCount: (readiness.groups ?? []).length
      };
    });
    const breadcrumbs = [{ label: "Setup" }];
    head("1qbhs0z", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>System Setup · NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-7 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Badge($$renderer4, {
              variant: "secondary",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(readiness.overall_percent ?? 0)}% of checks passed`);
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            tourKey: "setup-readiness-hub",
            context: readinessContext(),
            title: "System Setup",
            description: "Institutional readiness and cycle configuration checks organized by administrative workflow.",
            actions
          });
        }
        $$renderer3.push(`<!----> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            "data-tour": "setup-readiness-overview",
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  class: "border-border/60 border-b pb-4",
                  children: ($$renderer5) => {
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        class: "text-base",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Readiness`);
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
                          $$renderer6.push(`<!---->${escape_html(readiness.passed ?? 0)} of ${escape_html(readiness.total ?? 0)} checks passed.`);
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
                  class: "pt-5",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="recessed-well h-3 overflow-hidden rounded-full p-0.5"${attr("aria-label", `${readiness.passed ?? 0} of ${readiness.total ?? 0} setup checks passed`)}><div class="bg-primary h-full rounded-full transition-all shadow-xs"${attr_style(`width: ${readiness.overall_percent ?? 0}%`)}></div></div>`);
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
        $$renderer3.push(` <!--[-->`);
        const each_array = ensure_array_like(readiness.groups ?? []);
        for (let $$index_2 = 0, $$length = each_array.length; $$index_2 < $$length; $$index_2++) {
          let group = each_array[$$index_2];
          $$renderer3.push(`<section class="space-y-3"${attr("aria-labelledby", `${group.key}-heading`)} data-tour="setup-readiness-phases"><div><h2${attr("id", `${group.key}-heading`)} class="text-lg font-semibold">${escape_html(group.label)}</h2> <p class="text-muted-foreground text-sm">${escape_html(group.key === "exam_preparation" ? "Prepare the materials, rooms, and result output used for exam operations." : "Maintain the catalogs and policies that govern intake and admission.")}</p></div> <ul class="grid gap-3 lg:grid-cols-2" data-tour="setup-readiness-checklist"><!--[-->`);
          const each_array_1 = ensure_array_like(group.categories ?? []);
          for (let $$index_1 = 0, $$length2 = each_array_1.length; $$index_1 < $$length2; $$index_1++) {
            let category = each_array_1[$$index_1];
            const checks = category.checks ?? [];
            const status = categoryStatus({ ...category, checks });
            const open = expanded === category.key;
            const configure = canConfigure(category);
            $$renderer3.push(`<li class="craft-card-depth self-start overflow-hidden rounded-xl border border-border/70 transition-all duration-200"><button type="button" class="hover:bg-muted/40 flex min-h-16 w-full items-center gap-3 px-4 py-3 text-left"${attr("aria-expanded", open)}>`);
            if (open) {
              $$renderer3.push("<!--[0-->");
              Chevron_down($$renderer3, { class: "text-muted-foreground size-4 shrink-0" });
            } else {
              $$renderer3.push("<!--[-1-->");
              Chevron_right($$renderer3, { class: "text-muted-foreground size-4 shrink-0" });
            }
            $$renderer3.push(`<!--]--> <span class="min-w-0 flex-1"><span class="block font-medium">${escape_html(category.label)}</span> <span class="text-muted-foreground block text-xs">${escape_html(checks.filter((check) => check.passed).length)} of ${escape_html(checks.length)} checks
                                        passed</span></span> `);
            if (category.capability === "assessment") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="text-muted-foreground text-[10px] tracking-wide uppercase">TA</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            Badge($$renderer3, {
              variant: status === "critical" ? "destructive" : status === "attention" ? "outline" : "secondary",
              class: "capitalize",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></button> `);
            if (open) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border/60 bg-muted/20 space-y-3 border-t px-4 py-4"><!--[-->`);
              const each_array_2 = ensure_array_like(checks);
              for (let $$index = 0, $$length3 = each_array_2.length; $$index < $$length3; $$index++) {
                let check = each_array_2[$$index];
                $$renderer3.push(`<div class="flex items-start justify-between gap-3 text-sm"><div class="flex items-start gap-2 min-w-0 flex-1">`);
                if (check.passed) {
                  $$renderer3.push("<!--[0-->");
                  Circle_check($$renderer3, { class: "text-primary mt-0.5 size-4 shrink-0" });
                } else {
                  $$renderer3.push("<!--[-1-->");
                  Circle_alert($$renderer3, {
                    class: `mt-0.5 size-4 shrink-0 ${check.severity === "critical" ? "text-destructive" : "text-amber-600 dark:text-amber-400"}`
                  });
                }
                $$renderer3.push(`<!--]--> <div class="min-w-0"><p class="font-medium">${escape_html(check.label)}</p> `);
                if (check.detail) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="text-muted-foreground text-xs mt-0.5">${escape_html(check.detail)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div></div> `);
                if (check.action_href && configure) {
                  $$renderer3.push("<!--[0-->");
                  Button($$renderer3, {
                    href: check.action_href,
                    size: "sm",
                    variant: "ghost",
                    class: "shrink-0 h-7 text-xs text-primary font-medium hover:bg-primary/10 px-2",
                    children: ($$renderer4) => {
                      $$renderer4.push(`<!---->${escape_html(check.action_label ?? "Configure")} →`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (configure) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="flex min-w-0 flex-wrap items-center gap-2 pt-2 border-t border-border/40">`);
                if (category.key === "public_communications") {
                  $$renderer3.push("<!--[0-->");
                  if (capabilities.announcements || capabilities.setup) {
                    $$renderer3.push("<!--[0-->");
                    Button($$renderer3, {
                      href: "/communications",
                      size: "sm",
                      variant: "outline",
                      class: "min-h-9",
                      children: ($$renderer4) => {
                        $$renderer4.push(`<!---->Manage announcements`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> `);
                  if (capabilities.public_policies || capabilities.setup) {
                    $$renderer3.push("<!--[0-->");
                    Button($$renderer3, {
                      href: "/communications/policies",
                      size: "sm",
                      variant: "outline",
                      class: "min-h-9",
                      children: ($$renderer4) => {
                        $$renderer4.push(`<!---->Manage public policies`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> `);
                  if (capabilities.setup) {
                    $$renderer3.push("<!--[0-->");
                    Button($$renderer3, {
                      href: "/setup/privacy",
                      size: "sm",
                      variant: "secondary",
                      class: "min-h-9",
                      children: ($$renderer4) => {
                        $$renderer4.push(`<!---->Privacy policy`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]-->`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                  Button($$renderer3, {
                    href: category.key === "admission_support" ? "/setup/admission-support" : category.href,
                    size: "sm",
                    variant: "outline",
                    class: "min-h-9",
                    children: ($$renderer4) => {
                      $$renderer4.push(`<!---->${escape_html(category.key === "admission_support" ? "Manage admission models" : `Open ${category.label}`)}`);
                    },
                    $$slots: { default: true }
                  });
                }
                $$renderer3.push(`<!--]--> `);
                if (category.key === "aptitude") {
                  $$renderer3.push("<!--[0-->");
                  Button($$renderer3, {
                    href: "/setup/test-instruments",
                    size: "sm",
                    variant: "secondary",
                    class: "min-h-9",
                    children: ($$renderer4) => {
                      $$renderer4.push(`<!---->Test instruments`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> `);
                if (category.key === "xiam_knowledge_governance") {
                  $$renderer3.push("<!--[0-->");
                  Button($$renderer3, {
                    href: "/setup/ai-center",
                    size: "sm",
                    variant: "secondary",
                    class: "min-h-9",
                    children: ($$renderer4) => {
                      $$renderer4.push(`<!---->Open AI Control Center`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground text-xs">${escape_html(category.capability === "assessment" ? "Test Administrator access is required to open this page." : category.capability === "quota" ? "Program quota access is required to open this page." : category.capability === "knowledge" ? "Knowledge-source access is required to open this page." : "Institutional setup access is required to open this page.")}</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (category.key === "admission_support" && capabilities.consultation_policy) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="border-t border-border/40 pt-2">`);
                Button($$renderer3, {
                  href: "/setup/consultation-policy",
                  size: "sm",
                  variant: "outline",
                  class: "min-h-9",
                  children: ($$renderer4) => {
                    $$renderer4.push(`<!---->Online consultation settings`);
                  },
                  $$slots: { default: true }
                });
                $$renderer3.push(`<!----></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></li>`);
          }
          $$renderer3.push(`<!--]--></ul></section>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Index as default
};
