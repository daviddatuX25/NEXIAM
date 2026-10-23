import { h as head, e as escape_html, a as ensure_array_like, b as attr } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { B as BentoCard } from "./BentoCard-COpmyjxA.js";
import { S as Shield } from "./shield-DTtuUihb.js";
import { D as Download } from "./download-n32NlL8c.js";
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
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { audit } = $$props;
    const breadcrumbs = [{ label: "Audit Log" }];
    let filterEvent = audit.filters.event ?? "";
    let filterDateFrom = audit.filters.date_from ?? "";
    let filterDateTo = audit.filters.date_to ?? "";
    let filterSearch = audit.filters.search ?? "";
    head("1ni8ofo", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Security Audit Trail • NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6 lg:p-8"><div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60"><div><div class="flex items-center gap-2">`);
        Shield($$renderer3, { class: "size-6 text-primary" });
        $$renderer3.push(`<!----> <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Audit Log Explorer</h1></div> <p class="text-xs text-muted-foreground mt-1">Immutable, chronological record of system events, authentication, and administrative actions.</p></div> <div class="flex items-center gap-2 font-mono text-xs"><button class="px-3.5 py-1.5 rounded-xl border border-border bg-card hover:bg-muted font-bold text-foreground flex items-center gap-1.5 shadow-sm">`);
        Download($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> Export Filtered CSV</button></div></div> `);
        BentoCard($$renderer3, {
          class: "p-5 text-xs space-y-3",
          children: ($$renderer4) => {
            $$renderer4.push(`<div class="grid grid-cols-1 sm:grid-cols-4 gap-3"><div><label class="block font-mono text-[10px] text-muted-foreground font-bold uppercase mb-1">Event Type</label> `);
            $$renderer4.select(
              {
                value: filterEvent,
                class: "w-full text-xs rounded-xl border border-border bg-card px-3 py-1.5 font-mono text-foreground"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`All Events (${escape_html(audit.events.length)})`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array = ensure_array_like(audit.events);
                for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                  let ev = each_array[$$index];
                  $$renderer5.option({ value: ev }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(ev)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</div> <div><label class="block font-mono text-[10px] text-muted-foreground font-bold uppercase mb-1">Date From</label> <input type="date"${attr("value", filterDateFrom)} class="w-full text-xs rounded-xl border border-border bg-card px-3 py-1.5 font-mono text-foreground"/></div> <div><label class="block font-mono text-[10px] text-muted-foreground font-bold uppercase mb-1">Date To</label> <input type="date"${attr("value", filterDateTo)} class="w-full text-xs rounded-xl border border-border bg-card px-3 py-1.5 font-mono text-foreground"/></div> <div><label class="block font-mono text-[10px] text-muted-foreground font-bold uppercase mb-1">Search Actor / Text</label> <input type="text"${attr("value", filterSearch)} placeholder="Search email, ID, or keyword..." class="w-full text-xs rounded-xl border border-border bg-card px-3 py-1.5 text-foreground"/></div></div> <div class="flex justify-end gap-2 pt-2 border-t border-border/50"><button class="px-3 py-1.5 rounded-lg text-muted-foreground hover:text-foreground font-medium text-xs">Reset</button> <button class="px-4 py-1.5 rounded-xl bg-primary text-primary-foreground font-bold text-xs shadow-sm">Apply Filters</button></div>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        BentoCard($$renderer3, {
          class: "p-0 overflow-hidden text-xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<table class="w-full text-left border-collapse"><thead class="bg-muted/60 text-muted-foreground font-bold border-b border-border text-[11px] font-mono"><tr><th class="p-3.5">Timestamp</th><th class="p-3.5">Actor</th><th class="p-3.5">Event Key</th><th class="p-3.5">Summary</th><th class="p-3.5 text-center">Payload</th></tr></thead><tbody class="divide-y divide-border">`);
            const each_array_1 = ensure_array_like(audit.logs);
            if (each_array_1.length !== 0) {
              $$renderer4.push("<!--[-->");
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let log = each_array_1[$$index_1];
                $$renderer4.push(`<tr class="hover:bg-muted/30 transition-colors"><td class="p-3.5 font-mono text-muted-foreground text-[11px]">${escape_html(log.created_at_human)}</td><td class="p-3.5 font-medium text-foreground">${escape_html(log.actor)}</td><td class="p-3.5"><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-mono text-[10px] font-bold border border-primary/20">${escape_html(log.event_type)}</span></td><td class="p-3.5 text-foreground">${escape_html(log.payload?.summary ?? log.payload?.event ?? "—")}</td><td class="p-3.5 text-center"><button class="px-2.5 py-1 rounded-lg border border-border bg-card hover:bg-muted font-mono text-[10px] font-bold text-primary">{ JSON }</button></td></tr>`);
              }
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push(`<tr><td colspan="5" class="py-12 text-center text-muted-foreground font-mono">No audit events match these filters.</td></tr>`);
            }
            $$renderer4.push(`<!--]--></tbody></table>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
    });
  });
}
export {
  Index as default
};
