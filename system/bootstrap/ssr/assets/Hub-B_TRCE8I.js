import { g as sanitize_props, i as spread_props, j as slot, d as derived, h as head, e as escape_html, a as ensure_array_like, b as attr, c as attr_class, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import "clsx";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { U as Users } from "./users-DWlguTa_.js";
import { M as Megaphone } from "./megaphone-C8QUuT5s.js";
import { S as Settings_2 } from "./settings-2-B2lTvmrM.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_up_right } from "./arrow-up-right-BtyWsOyK.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./circle-alert-jWXJ7oC0.js";
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
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Chart_column($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M3 3v16a2 2 0 0 0 2 2h16" }],
    ["path", { "d": "M18 17V9" }],
    ["path", { "d": "M13 17V5" }],
    ["path", { "d": "M8 17v-3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "chart-column" },
    $$sanitized_props,
    {
      /**
       * @component @name ChartColumn
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMyAzdjE2YTIgMiAwIDAgMCAyIDJoMTYiIC8+CiAgPHBhdGggZD0iTTE4IDE3VjkiIC8+CiAgPHBhdGggZD0iTTEzIDE3VjUiIC8+CiAgPHBhdGggZD0iTTggMTd2LTMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/chart-column
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
function Hub($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      program,
      cycles = [],
      selected_cycle_id = null,
      quota = null,
      can_manage_quota = false,
      announcements = [],
      policies = [],
      benchmarks = null
    } = $$props;
    const tabs = [
      {
        id: "overview",
        label: "Overview & description",
        icon: Book_open
      },
      { id: "quotas", label: "Quota governance", icon: Users },
      { id: "announcements", label: "Announcements", icon: Megaphone },
      { id: "policies", label: "Policies", icon: Settings_2 },
      {
        id: "benchmarks",
        label: "Benchmarks / TabFM",
        icon: Chart_column
      }
    ];
    let activeTab = "overview";
    let selectedCycleId = selected_cycle_id === null ? "" : String(selected_cycle_id);
    const quotaRow = derived(() => quota ?? {});
    const capacity = derived(() => quotaRow().slots ?? null);
    const programDescription = derived(() => program.description ?? program.public_profile?.description ?? "");
    const majorCodes = derived(() => Array.isArray(program.major_codes) ? program.major_codes : []);
    useForm({
      program_id: String(program.id),
      academic_cycle_id: selectedCycleId,
      slots: capacity() ?? "",
      notice: ""
    });
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Programs", href: "/setup/programs" },
      { label: `${program.code} Hub` }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("miydp0", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(program.code)} Hub · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto grid w-full max-w-7xl gap-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: `${program.code} — ${program.name}`,
            description: `Department of ${program.department?.name ?? "—"}. Program overview, admissions quota governance, announcements, policies, and benchmarks.`
          });
          $$renderer4.push(`<!----> <div class="min-w-0 space-y-5"><div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"><div class="inline-flex min-w-full gap-1 rounded-xl border border-border/70 bg-muted/30 p-1 sm:min-w-0" role="tablist" aria-label="Program hub sections"><!--[-->`);
          const each_array = ensure_array_like(tabs);
          for (let index = 0, $$length = each_array.length; index < $$length; index++) {
            let tab = each_array[index];
            $$renderer4.push(`<button${attr("id", `program-hub-tab-${tab.id}`)} type="button" role="tab"${attr("aria-selected", activeTab === tab.id)} aria-controls="program-hub-panel"${attr("tabindex", activeTab === tab.id ? 0 : -1)}${attr_class(`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors ${activeTab === tab.id ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-background/70 hover:text-foreground"}`)}>`);
            if (tab.icon) {
              $$renderer4.push("<!--[-->");
              tab.icon($$renderer4, { class: "size-4", "aria-hidden": "true" });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` ${escape_html(tab.label)}</button>`);
          }
          $$renderer4.push(`<!--]--></div></div> <section id="program-hub-panel" role="tabpanel"${attr("aria-labelledby", `program-hub-tab-${activeTab}`)} tabindex="0" class="min-w-0 space-y-5 outline-none">`);
          {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="program-overview-heading"><div class="flex flex-wrap items-start justify-between gap-4"><div class="min-w-0 space-y-4"><div><h2 id="program-overview-heading" class="text-base font-semibold">Program overview</h2> <p class="mt-1 text-sm text-muted-foreground">${escape_html(programDescription() || "No public description has been added for this program.")}</p></div> <dl class="grid gap-4 text-sm sm:grid-cols-2"><div class="space-y-1"><dt class="text-muted-foreground">Department</dt> <dd class="font-medium">${escape_html(program.department?.name ?? "—")}</dd></div> <div class="space-y-1"><dt class="text-muted-foreground">Major codes</dt> <dd class="flex flex-wrap gap-1.5">`);
            if (majorCodes().length) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<!--[-->`);
              const each_array_1 = ensure_array_like(majorCodes());
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let code = each_array_1[$$index_1];
                Badge($$renderer4, {
                  variant: "outline",
                  class: "font-mono",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(code)}`);
                  },
                  $$slots: { default: true }
                });
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<span>—</span>`);
            }
            $$renderer4.push(`<!--]--></dd></div></dl></div> `);
            Link($$renderer4, {
              href: `/setup/programs/${program.id}/public-details`,
              children: ($$renderer5) => {
                Button($$renderer5, {
                  variant: "outline",
                  class: "min-h-11 gap-2",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Edit public details `);
                    Arrow_up_right($$renderer6, { class: "size-4", "aria-hidden": "true" });
                    $$renderer6.push(`<!---->`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></section>`);
          }
          $$renderer4.push(`<!--]--></section></div></div>`);
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
  Hub as default
};
