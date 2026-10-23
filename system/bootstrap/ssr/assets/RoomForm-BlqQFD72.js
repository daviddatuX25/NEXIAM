import { c as attr_class, b as attr, e as escape_html, B as Button } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function RoomForm($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { room = null } = $$props;
    const editing = Boolean(room);
    const form = useForm({
      code: room?.code ?? "",
      name: room?.name ?? "",
      space_kind: room?.space_kind ?? "room",
      capacity: room?.capacity ?? 30,
      is_active: room?.is_active ?? true
    });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<form class="space-y-6"><div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start"><div class="border-border bg-card space-y-5 rounded-xl border p-5 sm:p-6 shadow-xs"><div class="space-y-2">`);
      Label($$renderer3, {
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Space kind`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs font-mono"><label${attr_class(`border rounded-lg p-3 cursor-pointer transition-all flex flex-col justify-between ${form.space_kind === "room" ? "border-primary bg-primary/10" : "border-border bg-card"}`)}><div class="flex items-center justify-between"><span class="font-bold text-foreground">Exam Room</span> <input type="radio"${attr("checked", form.space_kind === "room", true)} value="room" class="accent-primary"/></div> <span class="text-[11px] text-muted-foreground mt-1">Has desks &amp; seating grid</span></label> <label${attr_class(`border rounded-lg p-3 cursor-pointer transition-all flex flex-col justify-between ${form.space_kind === "assembly_venue" ? "border-primary bg-primary/10" : "border-border bg-card"}`)}><div class="flex items-center justify-between"><span class="font-bold text-foreground">Assembly Venue</span> <input type="radio"${attr("checked", form.space_kind === "assembly_venue", true)} value="assembly_venue" class="accent-primary"/></div> <span class="text-[11px] text-muted-foreground mt-1">Covered court, gym, hall</span></label> <label${attr_class(`border rounded-lg p-3 cursor-pointer transition-all flex flex-col justify-between ${form.space_kind === "service_point" ? "border-primary bg-primary/10" : "border-border bg-card"}`)}><div class="flex items-center justify-between"><span class="font-bold text-foreground">Service Window</span> <input type="radio"${attr("checked", form.space_kind === "service_point", true)} value="service_point" class="accent-primary"/></div> <span class="text-[11px] text-muted-foreground mt-1">Intake counter, cashier</span></label></div> `);
      if (form.space_kind !== "room") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-[11px] text-muted-foreground font-mono">⚡ Seating arrangement grids are automatically bypassed for this space kind.</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "code",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Room code`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "code",
        disabled: editing,
        maxlength: 32,
        placeholder: "RM-102",
        class: "min-h-11 font-mono",
        required: true,
        get value() {
          return form.code;
        },
        set value($$value) {
          form.code = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.code) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.code)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "capacity",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Capacity`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "capacity",
        type: "number",
        min: "1",
        max: "500",
        class: "min-h-11",
        required: true,
        get value() {
          return form.capacity;
        },
        set value($$value) {
          form.capacity = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.capacity) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.capacity)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "name",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Room name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "name",
        maxlength: 255,
        placeholder: "Main Hall 102",
        class: "min-h-11",
        required: true,
        get value() {
          return form.name;
        },
        set value($$value) {
          form.name = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.name) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.name)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (editing) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<label class="border-border flex items-start gap-3 rounded-lg border p-4"><input type="checkbox"${attr("checked", form.is_active, true)} class="accent-primary mt-1 h-4 w-4"/> <span><span class="block text-sm font-medium">Active for scheduling</span><span class="text-muted-foreground block text-xs">Rooms with future sessions or incompatible defaults cannot be deactivated.</span></span></label> `);
        if (form.errors.is_active) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.is_active)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-2 pt-1">`);
      Button($$renderer3, {
        type: "submit",
        disabled: form.processing,
        class: "min-h-11",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(form.processing ? "Saving…" : editing ? "Save room" : "Create room")}`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: editing ? `/setup/rooms/${room.id}` : "/setup/rooms",
        children: ($$renderer4) => {
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "min-h-11",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div></div> <div class="border-border bg-card space-y-5 rounded-xl border p-5 sm:p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h3 class="font-semibold text-sm text-foreground">Venue Specifications</h3> <p class="text-xs text-muted-foreground">Configuration and operational behavior</p></div> <div class="space-y-3 text-sm"><div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Space Kind</span> <span class="font-medium text-foreground capitalize">${escape_html(form.space_kind.replace("_", " "))}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Venue Code</span> <span class="font-mono text-xs font-semibold text-foreground">${escape_html(form.code || "Pending")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Capacity</span> <span class="font-medium text-foreground">${escape_html(form.capacity)} attendees</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Seating Layout</span> <span class="font-medium text-foreground">${escape_html(form.space_kind === "room" ? "Custom grid supported" : "Bypassed (open seating)")}</span></div></div> <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Venue capacity sets the hard upper ceiling during examination scheduling and applicant auto-distribution. Exam rooms support deterministic desk assignments, while assembly venues and service windows utilize bulk attendance without seating grids.</div></div></div></form>`);
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
  RoomForm as default
};
