import { c as attr_class, b as attr, s as stringify } from "../app.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
function InfoPopover($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      content = "",
      title = "",
      align = "center",
      class: className = ""
    } = $$props;
    let open = false;
    "tip-" + Math.random().toString(36).slice(2, 9);
    $$renderer2.push(`<span${attr_class(`relative inline-flex items-center ${stringify(className)}`)}><button type="button" class="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"${attr("aria-expanded", open)}${attr("aria-describedby", void 0)} aria-label="More information">`);
    Circle_question_mark($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----></button> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></span>`);
  });
}
export {
  InfoPopover as I
};
