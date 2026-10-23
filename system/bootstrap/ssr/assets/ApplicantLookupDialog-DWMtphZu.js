import { g as sanitize_props, i as spread_props, j as slot, b as attr, c as attr_class, B as Button, e as escape_html } from "../app.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { I as Input } from "./input-BUZAZKIK.js";
import "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import "clsx";
import { Q as QrScanner } from "./StaffLayout-CR-MZopa.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { router } from "@inertiajs/core";
function Clipboard_pen($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 4h2a2 2 0 0 1 2 2v2" }],
    [
      "path",
      {
        "d": "M21.34 15.664a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"
      }
    ],
    ["path", { "d": "M8 22H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" }],
    [
      "rect",
      { "x": "8", "y": "2", "width": "8", "height": "4", "rx": "1" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "clipboard-pen" },
    $$sanitized_props,
    {
      /**
       * @component @name ClipboardPen
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgNGgyYTIgMiAwIDAgMSAyIDJ2MiIgLz4KICA8cGF0aCBkPSJNMjEuMzQgMTUuNjY0YTEgMSAwIDEgMC0zLjAwNC0zLjAwNGwtNS4wMSA1LjAxMmEyIDIgMCAwIDAtLjUwNi44NTRsLS44MzcgMi44N2EuNS41IDAgMCAwIC42Mi42MmwyLjg3LS44MzdhMiAyIDAgMCAwIC44NTQtLjUwNnoiIC8+CiAgPHBhdGggZD0iTTggMjJINmEyIDIgMCAwIDEtMi0yVjZhMiAyIDAgMCAxIDItMmgyIiAvPgogIDxyZWN0IHg9IjgiIHk9IjIiIHdpZHRoPSI4IiBoZWlnaHQ9IjQiIHJ4PSIxIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/clipboard-pen
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
function ConsultationMobileDock($$renderer, $$props) {
  let {
    mode = "index",
    onSearch = () => {
    },
    listHref = "/consultation",
    onRecord = () => {
    },
    recordDisabled = false
  } = $$props;
  $$renderer.push(`<nav aria-label="Consultation mobile actions" class="fixed inset-x-0 bottom-0 z-40 w-full border-t border-border/80 bg-background/95 backdrop-blur-md pb-[env(safe-area-inset-bottom)] lg:hidden md:pl-56">`);
  if (mode === "detail") {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<div class="relative mx-auto grid h-16 max-w-md grid-cols-3 items-center px-4"><div class="flex justify-start">`);
    Link($$renderer, {
      href: listHref,
      class: "flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
      children: ($$renderer2) => {
        Arrow_left($$renderer2, { class: "size-5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> <span>List</span>`);
      },
      $$slots: { default: true }
    });
    $$renderer.push(`<!----></div> <div class="flex flex-col items-center justify-center"><button type="button" aria-label="Search applicants" class="-mt-6 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">`);
    Search($$renderer, { class: "size-6", "aria-hidden": "true" });
    $$renderer.push(`<!----></button> <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">Search</span></div> <div class="flex justify-end"><button type="button"${attr("disabled", recordDisabled, true)} aria-label="Record consultation"${attr("title", recordDisabled ? "Finalize the result before recording consultation" : "Record consultation")} class="flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-foreground transition-colors hover:text-primary active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40">`);
    Clipboard_pen($$renderer, { class: "size-5", "aria-hidden": "true" });
    $$renderer.push(`<!----> <span>Record</span></button></div></div>`);
  } else {
    $$renderer.push("<!--[-1-->");
    $$renderer.push(`<div class="relative mx-auto flex h-16 max-w-md items-center justify-center px-4"><div class="flex flex-col items-center justify-center"><button type="button" aria-label="Search applicants" class="-mt-6 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">`);
    Search($$renderer, { class: "size-6", "aria-hidden": "true" });
    $$renderer.push(`<!----></button> <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">Search</span></div></div>`);
  }
  $$renderer.push(`<!--]--></nav>`);
}
function ApplicantLookupDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false, onOpenChange = () => {
    } } = $$props;
    let lookupMode = "camera";
    let manualCode = "";
    let resolvingCode = false;
    let resolveError = "";
    async function resolveScannedCode(codeString) {
      if (!codeString || resolvingCode) return;
      resolvingCode = true;
      resolveError = "";
      try {
        const { data } = await window.axios.post("/staff/scan/resolve", { code: codeString });
        if (data.scan_result?.state === "resolved" && data.scanned_attempt?.id) {
          onOpenChange(false);
          router.visit(`/consultation/applicants/${data.scanned_attempt.id}`);
          return;
        }
        resolveError = data.scan_result?.message ?? "Applicant code could not be resolved.";
      } catch (err) {
        resolveError = "Failed to verify applicant QR code.";
      } finally {
        resolvingCode = false;
      }
    }
    function handleManualCodeSubmit() {
      if (!manualCode.trim()) return;
      resolveScannedCode(manualCode.trim());
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Dialog($$renderer3, {
        open,
        onOpenChange,
        children: ($$renderer4) => {
          Dialog_content($$renderer4, {
            class: "flex max-h-[92dvh] flex-col overflow-hidden p-0 sm:max-w-xl rounded-2xl border border-border/80 bg-background shadow-2xl",
            children: ($$renderer5) => {
              $$renderer5.push(`<div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3"><div class="pr-8">`);
              Dialog_title($$renderer5, {
                class: "text-base font-bold tracking-tight text-foreground flex items-center gap-2",
                children: ($$renderer6) => {
                  {
                    $$renderer6.push("<!--[0-->");
                    Camera($$renderer6, { class: "size-4 text-primary" });
                  }
                  $$renderer6.push(`<!--]--> <span>Examinee Lookup &amp; QR Scanner</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Dialog_description($$renderer5, {
                class: "text-xs text-muted-foreground mt-0.5",
                children: ($$renderer6) => {
                  {
                    $$renderer6.push("<!--[0-->");
                    $$renderer6.push(`Scan physical result sheet QR code or enter candidate locator code.`);
                  }
                  $$renderer6.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div> <div class="mt-3.5 grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="Lookup mode"><button type="button" role="tab"${attr("aria-selected", lookupMode === "camera")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${"bg-background text-foreground shadow-xs font-bold"}`)}>`);
              Camera($$renderer5, { class: "size-3.5" });
              $$renderer5.push(`<!----> <span>Camera Scanner</span></button> <button type="button" role="tab"${attr("aria-selected", lookupMode === "manual")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${"text-muted-foreground hover:text-foreground"}`)}>`);
              Search($$renderer5, { class: "size-3.5" });
              $$renderer5.push(`<!----> <span>Manual Search</span></button></div></div> <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">`);
              {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="flex flex-col items-center space-y-3.5 w-full"><div class="relative w-full max-w-[440px] mx-auto">`);
                QrScanner($$renderer5, {
                  active: open && lookupMode === "camera",
                  paused: resolvingCode,
                  onScan: resolveScannedCode,
                  class: "w-full shadow-inner"
                });
                $$renderer5.push(`<!----> `);
                if (resolvingCode) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">`);
                  Clock($$renderer5, { class: "size-6 animate-spin text-primary" });
                  $$renderer5.push(`<!----> <span class="text-xs font-semibold text-foreground">Resolving examinee QR…</span></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div> `);
                if (resolveError) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="w-full max-w-[440px] mx-auto rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">`);
                  Circle_alert($$renderer5, { class: "size-4 shrink-0 mt-0.5" });
                  $$renderer5.push(`<!----> <span class="font-medium">${escape_html(resolveError)}</span></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5"><div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">Can't scan QR? Enter Applicant Number</div> <div class="flex gap-2">`);
                Input($$renderer5, {
                  placeholder: "e.g. APP-2026-00005",
                  class: "h-9 text-xs font-mono",
                  onkeydown: (e) => {
                    if (e.key === "Enter") handleManualCodeSubmit();
                  },
                  get value() {
                    return manualCode;
                  },
                  set value($$value) {
                    manualCode = $$value;
                    $$settled = false;
                  }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  size: "sm",
                  class: "h-9 px-3 text-xs font-semibold shrink-0 gap-1",
                  disabled: !manualCode.trim() || resolvingCode,
                  onclick: handleManualCodeSubmit,
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Resolve `);
                    Arrow_right($$renderer6, { class: "size-3" });
                    $$renderer6.push(`<!---->`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----></div></div></div>`);
              }
              $$renderer5.push(`<!--]--></div>`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
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
  ApplicantLookupDialog as A,
  ConsultationMobileDock as C
};
