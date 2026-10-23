import { h as head, a as ensure_array_like, b as attr, e as escape_html } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { offices = [] } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Report Templates" }
    ];
    head("1xi6mmx", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Report Templates · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><header class="space-y-1"><h1 class="text-2xl font-semibold tracking-tight text-foreground">Report Templates</h1> <p class="text-sm text-muted-foreground">Manage the official report layouts for each office.</p></header> <!--[-->`);
        const each_array = ensure_array_like(offices);
        for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
          let office = each_array[$$index_1];
          $$renderer3.push(`<section class="space-y-3"${attr("aria-labelledby", `office-${office.office ?? office.office_key}`)}><h2${attr("id", `office-${office.office ?? office.office_key}`)} class="text-lg font-semibold text-foreground">${escape_html(office.office_label ?? office.label ?? (office.office === "registrar" ? "Registrar Office" : "Guidance Office"))}</h2> <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"><!--[-->`);
          const each_array_1 = ensure_array_like(office.surfaces ?? []);
          for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
            let surface = each_array_1[$$index];
            $$renderer3.push(`<article class="grid min-w-0 gap-4 rounded-xl border border-border bg-card p-5 shadow-sm sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">`);
            MaterialDocumentPreview($$renderer3, {
              src: surface.active_template?.thumbnail_url,
              kind: "report",
              mode: surface.active_template?.mode?.toUpperCase() ?? "HTML",
              title: surface.active_template?.name ?? surface.title,
              builder_json: surface.active_template?.builder_json,
              orientation: surface.active_template?.orientation ?? "portrait",
              alt: `${surface.title} active template preview`
            });
            $$renderer3.push(`<!----> <div class="space-y-3"><div class="flex flex-wrap items-start justify-between gap-2"><h3 class="text-base font-semibold text-foreground">${escape_html(surface.title)}</h3> <span class="rounded-md bg-muted px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">${escape_html(surface.layout_paradigm)}</span></div> <div class="rounded-lg border border-border/70 bg-muted/30 px-3 py-2.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Active template</p> <p class="mt-1 text-sm font-medium text-foreground">${escape_html(surface.active_template?.name ?? "No active template")}</p></div> <p class="text-xs text-muted-foreground">${escape_html(surface.templates_count ?? surface.template_count ?? 0)} template versions</p></div> `);
            Link($$renderer3, {
              href: `/setup/report-templates/${surface.surface_key}`,
              class: "inline-flex min-h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Manage Templates`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></article>`);
          }
          $$renderer3.push(`<!--]--></div></section>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (offices.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-xl border border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground">No report surfaces are available.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Index as default
};
