import { b as attr, e as escape_html, a as ensure_array_like } from "../app.js";
import "clsx";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function BlankInstanceHistoryPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { templateVersionId = null } = $$props;
    let instances = [];
    let qrDisabledIssuances = [];
    let loading = false;
    let displayCode = "";
    let recoveryState = "undecodable";
    $$renderer2.push(`<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="blank-history-title"><div class="flex items-start justify-between gap-3"><div><h2 id="blank-history-title" class="font-semibold text-slate-950">Answer Sheet print history</h2> <p class="mt-1 text-sm text-slate-600">Copy policy and page claims for signed sheets, plus explicit
                QR-disabled issuance history.</p></div> <button type="button" class="rounded border border-slate-300 px-3 py-1.5 text-xs font-medium"${attr("disabled", loading, true)}>${escape_html("Refresh")}</button></div> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="mt-4 space-y-2">`);
    if (instances.length === 0 && qrDisabledIssuances.length === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="rounded border border-dashed border-slate-300 p-4 text-sm text-slate-600">No Answer Sheets printed from this version.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <!--[-->`);
    const each_array = ensure_array_like(instances);
    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
      let instance = each_array[$$index_1];
      $$renderer2.push(`<div class="rounded-lg border border-slate-200 p-3"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-mono text-xs font-semibold">${escape_html(instance.display_code)}</p> <p class="mt-1 text-xs text-slate-500">${escape_html(instance.copy_policy === "reusable_master" ? "Reusable master" : "Unique sheet")} · ${escape_html(instance.page_count)} page(s) `);
      if (instance.copy_policy === "reusable_master") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`· ${escape_html(instance.intended_copy_count)} intended copies`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></p></div> <div class="flex items-center gap-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-[11px]">${escape_html(instance.status)}</span> `);
      if (instance.status === "printed") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="text-xs font-medium text-red-700">Void</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div> <div class="mt-2 flex flex-wrap gap-1"><!--[-->`);
      const each_array_1 = ensure_array_like(instance.pages ?? []);
      for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
        let page = each_array_1[$$index];
        $$renderer2.push(`<span class="rounded bg-slate-50 px-2 py-1 text-[11px]">Page ${escape_html(page.page_no)}: ${escape_html(page.claim_count)}
                            ${escape_html(page.claim_count === 1 ? "claim" : "claims")} `);
        if (page.active_authorization_count > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`· ${escape_html(page.active_authorization_count)} authorized`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></span>`);
      }
      $$renderer2.push(`<!--]--></div></div>`);
    }
    $$renderer2.push(`<!--]--> <!--[-->`);
    const each_array_2 = ensure_array_like(qrDisabledIssuances);
    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
      let issuance = each_array_2[$$index_2];
      $$renderer2.push(`<div class="rounded-lg border border-slate-200 p-3"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-mono text-xs font-semibold">Issuance ${escape_html(issuance.issuance_code)}</p> <p class="mt-1 text-xs text-slate-500">QR-disabled print run · ${escape_html(issuance.page_count)} page(s) ·
                            ${escape_html(issuance.copy_policy === "reusable_master" ? "Reusable master" : "Unique-instance policy")} ·
                            ${escape_html(issuance.intended_copy_count)} intended copies</p></div> <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px]">${escape_html(issuance.status)}</span></div> `);
      if (issuance.artifact_manifest_hash) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-2 break-all text-[11px] text-slate-500">Artifact manifest SHA-256: ${escape_html(issuance.artifact_manifest_hash)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (issuance.download_sha256) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-1 break-all text-[11px] text-slate-500">Print bundle SHA-256: ${escape_html(issuance.download_sha256)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (issuance.failure_code) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-1 text-xs text-red-700">The print run failed. Queue a new print request to retry; this record is retained.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="mt-5 border-t border-slate-200 pt-4"><h3 class="text-sm font-semibold text-slate-950">Unreadable or absent QR</h3> <p class="mt-1 text-xs text-slate-600">Present-but-invalid signatures cannot be overridden. This fallback
            stays blocked until explicit confirmation.</p> <div class="mt-3 grid gap-2 sm:grid-cols-[150px_1fr_auto]">`);
      $$renderer2.select(
        {
          class: "rounded border-slate-300 text-sm",
          value: recoveryState
        },
        ($$renderer3) => {
          $$renderer3.option({ value: "undecodable" }, ($$renderer4) => {
            $$renderer4.push(`Undecodable`);
          });
          $$renderer3.option({ value: "absent" }, ($$renderer4) => {
            $$renderer4.push(`Absent`);
          });
          $$renderer3.option({ value: "present_invalid" }, ($$renderer4) => {
            $$renderer4.push(`Present but invalid`);
          });
        }
      );
      $$renderer2.push(` <input class="rounded border-slate-300 text-sm"${attr("value", displayCode)} placeholder="NI-… display code"/> <button class="rounded bg-slate-950 px-3 py-2 text-sm font-medium text-white" type="button">Resolve and confirm</button></div> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
export {
  BlankInstanceHistoryPanel as default
};
