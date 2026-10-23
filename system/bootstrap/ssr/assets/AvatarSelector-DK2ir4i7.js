import { e as escape_html, a as ensure_array_like, b as attr, c as attr_class, s as stringify, d as derived } from "../app.js";
import { A as AvatarBadge } from "./AvatarBadge-DQ5Qru9A.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function AvatarSelector($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      currentAvatar = { type: "initials", preset: null, url: null },
      name = ""
    } = $$props;
    const presets = [
      {
        key: "counselor",
        title: "Guidance Counselor",
        office: "Guidance Office"
      },
      {
        key: "guidance_clerk",
        title: "Guidance Clerk",
        office: "Guidance Office"
      },
      {
        key: "guidance_support",
        title: "Guidance Support Staff",
        office: "Guidance Office"
      },
      {
        key: "registrar_admin",
        title: "Registrar Administrator",
        office: "Registrar"
      },
      {
        key: "records",
        title: "Registrar Clerk",
        office: "Registrar"
      },
      {
        key: "registrar_support",
        title: "Registrar Support Staff",
        office: "Registrar"
      },
      {
        key: "program_head",
        title: "Program Head",
        office: "Academic Department"
      },
      { key: "dean", title: "Academic Dean", office: "Dean's Office" },
      {
        key: "hr_officer",
        title: "Human Resource Officer",
        office: "Human Resources"
      },
      {
        key: "exam_proctor",
        title: "Exam Proctor",
        office: "Examination Duty"
      }
    ];
    const modes = [
      { key: "presets", label: "Office SVG Presets" },
      { key: "upload", label: "Upload Photo" },
      { key: "initials", label: "Initials Monogram" }
    ];
    let mode = "presets";
    let processing = false;
    const previewAvatar = derived(() => currentAvatar);
    $$renderer2.push(`<section class="rounded-xl border border-border bg-card p-6 shadow-sm" aria-labelledby="avatar-customizer-title"><div class="mb-5 flex flex-wrap items-center gap-4">`);
    AvatarBadge($$renderer2, { avatar: previewAvatar(), name, size: "xl" });
    $$renderer2.push(`<!----> <div class="min-w-0 flex-1"><div class="flex items-center gap-2">`);
    Sparkles($$renderer2, { class: "size-4 text-primary", "aria-hidden": "true" });
    $$renderer2.push(`<!----> <h2 id="avatar-customizer-title" class="text-lg font-semibold text-foreground">Your staff avatar</h2></div> <p class="mt-1 text-sm text-muted-foreground">Choose an office illustration or use a local profile photo.</p> <p class="mt-1 truncate text-sm font-medium text-foreground">${escape_html(name || "Staff member")}</p></div></div> <div class="mb-5 grid grid-cols-1 gap-2 rounded-lg bg-muted/50 p-1 sm:grid-cols-3" role="group" aria-label="Avatar type"><!--[-->`);
    const each_array = ensure_array_like(modes);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let item = each_array[$$index];
      $$renderer2.push(`<button type="button"${attr("aria-pressed", mode === item.key)}${attr_class(`min-h-[44px] rounded-md px-3 py-2 text-sm font-medium transition-colors ${mode === item.key ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"}`)}>${escape_html(item.label)}</button>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><!--[-->`);
      const each_array_1 = ensure_array_like(presets);
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let preset = each_array_1[$$index_1];
        const selected = currentAvatar?.type === "preset" && currentAvatar?.preset === preset.key;
        $$renderer2.push(`<button type="button"${attr_class(`group flex min-h-32 flex-col items-center justify-center gap-2 rounded-xl border p-3 text-center transition ${selected ? "border-primary bg-primary/5 ring-2 ring-primary/20" : "border-border bg-background/60 hover:border-primary/50 hover:bg-accent/50"}`)}${attr("aria-pressed", selected)}${attr("aria-label", `Choose ${stringify(preset.title)} avatar`)}${attr("disabled", processing, true)}><span class="relative">`);
        AvatarBadge($$renderer2, {
          avatar: { type: "preset", preset: preset.key, url: null },
          name,
          size: "lg"
        });
        $$renderer2.push(`<!----> `);
        if (selected) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">`);
          Check($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!----></span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></span> <span class="text-xs font-semibold leading-tight text-foreground">${escape_html(preset.title)}</span> <span class="text-[11px] text-muted-foreground">${escape_html(preset.office)}</span></button>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
export {
  AvatarSelector as default
};
