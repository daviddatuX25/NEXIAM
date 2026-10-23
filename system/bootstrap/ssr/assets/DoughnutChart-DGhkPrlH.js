import { o as onDestroy, e as escape_html, f as attr_style } from "../app.js";
import "chart.js/auto";
function DoughnutChart($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { title = "", data = [], height = 220, cutout = "65%" } = $$props;
    onDestroy(() => {
    });
    $$renderer2.push(`<div class="space-y-2">`);
    if (title) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<h4 class="text-sm font-bold tracking-tight text-foreground">${escape_html(title)}</h4>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div${attr_style(`height: ${height}px`)} class="w-full relative flex items-center justify-center"><canvas></canvas></div></div>`);
  });
}
export {
  DoughnutChart as D
};
