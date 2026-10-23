import { l as bind_props, e as escape_html, b as attr, B as Button } from "../app.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import "clsx";
import "./safeMarkdownHtml-BkQMeMEI.js";
import { D as Download } from "./download-n32NlL8c.js";
import { E as External_link } from "./external-link-DmB8ndP3.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./Icon-Dal7PqKK.js";
import "dompurify";
import "marked";
function DemoReferenceViewer($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false, reference = null } = $$props;
    let status = "idle";
    let imageSource = "";
    let zoom = 100;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return open;
          },
          set open($$value) {
            open = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "flex h-[90dvh] w-[calc(100%-1rem)] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden border-slate-700 bg-slate-950 p-0 text-slate-100 sm:max-w-5xl",
                "aria-describedby": "guide-reference-description",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      class: "shrink-0 border-b border-slate-800 p-4 pr-12 sm:p-5 sm:pr-14",
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            class: "text-lg font-semibold text-slate-100",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(reference?.title ?? "Reference")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            id: "guide-reference-description",
                            class: "mt-1 text-sm leading-5 text-slate-400",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(reference?.note ?? "Read-only reference. Closing it keeps your workflow and selected step.")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (reference) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="mt-3 flex flex-wrap items-center gap-2"><a${attr("href", reference.url)} download="" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">`);
                          Download($$renderer6, { class: "size-3.5", "aria-hidden": "true" });
                          $$renderer6.push(`<!----> Download source</a> `);
                          if (reference.kind === "diagram") {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<a${attr("href", reference.url)} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">`);
                            External_link($$renderer6, { class: "size-3.5", "aria-hidden": "true" });
                            $$renderer6.push(`<!----> Open separately</a> <div class="flex items-center gap-1" aria-label="Diagram zoom">`);
                            Button($$renderer6, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                              "aria-label": "Zoom out",
                              disabled: zoom <= 50,
                              onclick: () => zoom = Math.max(50, zoom - 25),
                              children: ($$renderer7) => {
                                Minus($$renderer7, { class: "size-3.5", "aria-hidden": "true" });
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----> <span class="min-w-12 text-center text-xs tabular-nums">${escape_html(zoom)}%</span> `);
                            Button($$renderer6, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                              "aria-label": "Zoom in",
                              disabled: zoom >= 200,
                              onclick: () => zoom = Math.min(200, zoom + 25),
                              children: ($$renderer7) => {
                                Plus($$renderer7, { class: "size-3.5", "aria-hidden": "true" });
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----> `);
                            Button($$renderer6, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                              "aria-label": "Fit diagram width",
                              onclick: () => zoom = 100,
                              children: ($$renderer7) => {
                                Rotate_ccw($$renderer7, { class: "size-3.5", "aria-hidden": "true" });
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
                  $$renderer5.push(` <div id="guide-reference-content" class="relative min-h-0 flex-1 overflow-auto p-4 sm:p-6"${attr("aria-busy", status === "loading")}>`);
                  {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (reference?.kind === "diagram" && imageSource) ;
                  else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> <div class="shrink-0 border-t border-slate-800 p-3 text-right sm:px-5">`);
                  Button($$renderer5, {
                    type: "button",
                    variant: "outline",
                    class: "min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                    onclick: () => open = false,
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Close reference`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { open });
  });
}
export {
  DemoReferenceViewer as default
};
