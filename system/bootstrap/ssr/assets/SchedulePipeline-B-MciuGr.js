import { a as ensure_array_like, c as attr_class, e as escape_html } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle } from "./circle-DadbbVvn.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function SchedulePipeline($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { stages = [] } = $$props;
    $$renderer2.push(`<ol class="relative m-0 list-none space-y-0 p-0" aria-label="Exam schedule progress"><!--[-->`);
    const each_array = ensure_array_like(stages);
    for (let i = 0, $$length = each_array.length; i < $$length; i++) {
      let stage = each_array[i];
      const isLast = i === stages.length - 1;
      $$renderer2.push(`<li class="relative flex gap-3"><div class="flex shrink-0 flex-col items-center"><div class="relative z-10">`);
      if (stage.status === "current") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="absolute -inset-1.5 animate-ping rounded-full bg-primary/20 opacity-75" style="animation-duration: 3s;" aria-hidden="true"></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div${attr_class(`relative flex size-8 items-center justify-center rounded-full border-2 transition-colors ${stage.status === "done" ? "border-primary bg-primary text-primary-foreground" : stage.status === "current" ? "border-primary bg-background text-primary ring-4 ring-primary/10" : "border-muted-foreground/30 bg-background text-muted-foreground"}`)}>`);
      if (stage.status === "done") {
        $$renderer2.push("<!--[0-->");
        Circle_check($$renderer2, { class: "size-4", "aria-hidden": "true" });
      } else if (stage.status === "current") {
        $$renderer2.push("<!--[1-->");
        Circle($$renderer2, { class: "size-4 fill-primary/20", "aria-hidden": "true" });
      } else {
        $$renderer2.push("<!--[-1-->");
        Circle($$renderer2, { class: "size-4", "aria-hidden": "true" });
      }
      $$renderer2.push(`<!--]--></div></div> `);
      if (!isLast) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(`mt-1 min-h-7 w-0.5 flex-1 rounded-full ${stage.status === "done" ? "bg-primary" : stage.status === "current" ? "bg-primary/40" : "bg-border"}`)} aria-hidden="true"></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <div class="min-w-0 flex-1 pb-5 pt-0.5 last:pb-0"><div class="flex flex-wrap items-center gap-2"><span${attr_class(`text-sm font-medium ${stage.status === "upcoming" ? "text-muted-foreground" : "text-foreground"}`)}>${escape_html(stage.label)}</span> `);
      Badge($$renderer2, {
        variant: stage.status === "done" ? "outline" : stage.status === "current" ? "default" : "secondary",
        class: "shrink-0 text-[10px] uppercase tracking-wide",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(stage.status === "done" ? "Done" : stage.status === "current" ? "Now" : "Next")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> `);
      if (stage.summary) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-1 text-sm leading-relaxed text-muted-foreground">${escape_html(stage.summary)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></li>`);
    }
    $$renderer2.push(`<!--]--></ol>`);
  });
}
export {
  SchedulePipeline as default
};
