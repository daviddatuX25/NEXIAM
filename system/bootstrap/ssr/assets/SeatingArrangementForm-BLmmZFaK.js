import { d as derived, b as attr, e as escape_html, a as ensure_array_like, c as attr_class, B as Button } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import SeatingArrangementPreview from "./SeatingArrangementPreview-BlLfpBBH.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function SeatingArrangementForm($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      arrangement = null,
      rooms = [],
      patterns = [],
      availability_modes = [],
      referenced_facts = null,
      published_facts = null,
      conflict = null,
      back_href = "/setup/seating-arrangements"
    } = $$props;
    const editing = Boolean(arrangement?.id);
    function roomIdsFor(value) {
      if (Array.isArray(value?.room_ids)) return value.room_ids.map((id) => String(id));
      if (Array.isArray(value?.rooms)) {
        return value.rooms.map((room) => typeof room === "object" ? room.id : room).filter((id) => id !== null && id !== void 0).map((id) => String(id));
      }
      return [];
    }
    const patternOptions = derived(() => patterns.length > 0 ? patterns : [
      {
        value: "uniform",
        label: "Uniform A",
        description: "Every desk receives the same distribution variant."
      },
      {
        value: "checkerboard",
        label: "Checkerboard A/B",
        description: "Adjacent desks alternate A and B."
      },
      {
        value: "latin_four",
        label: "Latin four A–D",
        description: "Four adjacent variants repeat in a deterministic cycle."
      },
      {
        value: "linear_alternating",
        label: "Linear alternating A/B",
        description: "The row-major manifest alternates A and B."
      }
    ]);
    const availabilityOptions = derived(() => availability_modes.length > 0 ? availability_modes : [
      {
        value: "all_rooms",
        label: "All compatible rooms",
        description: "Available to every active room with enough capacity."
      },
      {
        value: "selected_rooms",
        label: "Selected rooms only",
        description: "Available only to the rooms you choose below."
      }
    ]);
    const form = useForm({
      name: arrangement?.name ?? "",
      rows: arrangement?.rows ?? 5,
      columns: arrangement?.columns ?? 6,
      booklet_pattern: arrangement?.booklet_pattern ?? patternOptions()[0]?.value ?? "checkerboard",
      availability_mode: arrangement?.availability_mode ?? availabilityOptions()[0]?.value ?? "all_rooms",
      room_ids: roomIdsFor(arrangement),
      is_active: arrangement?.is_active ?? true,
      lock_version: arrangement?.lock_version ?? 1
    });
    const deskCount = derived(() => Math.max(0, Number(form.rows || 0) * Number(form.columns || 0)));
    const activeRooms = derived(() => (rooms ?? []).filter((room) => room.is_active !== false));
    const selectedRoomIds = derived(() => (form.room_ids ?? []).map((id) => String(id)));
    const selectedRooms = derived(() => activeRooms().filter((room) => selectedRoomIds().includes(String(room.id))));
    const incompatibleAllRooms = derived(() => activeRooms().filter((room) => Number(room.capacity ?? 0) < deskCount()));
    const incompatibleSelectedRooms = derived(() => selectedRooms().filter((room) => Number(room.capacity ?? 0) < deskCount()));
    const compatibleActiveRooms = derived(() => activeRooms().filter((room) => Number(room.capacity ?? 0) >= deskCount()));
    const compatibleActiveRoomIds = derived(() => compatibleActiveRooms().map((room) => String(room.id)));
    const allCompatibleRoomsSelected = derived(() => compatibleActiveRoomIds().length > 0 && compatibleActiveRoomIds().every((id) => selectedRoomIds().includes(id)));
    const missingSelectedRooms = derived(() => selectedRoomIds().filter((id) => !activeRooms().some((room) => String(room.id) === id)));
    const availabilityError = derived(() => form.availability_mode === "selected_rooms" && selectedRoomIds().length === 0 ? "Select at least one compatible room or choose all compatible rooms." : null);
    const serverError = derived(() => form.errors?.availability_mode || form.errors?.room_ids || form.errors?.rows || form.errors?.columns || form.errors?.lock_version || form.errors?.conflict || null);
    const hasReferenceFacts = derived(() => Boolean(referenced_facts || published_facts));
    function roomLabel(room) {
      return room.label ?? room.name ?? room.code ?? `Room ${room.id}`;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)]"><form class="border-border bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-6"${attr("aria-describedby", serverError() || availabilityError() ? "arrangement-form-status" : void 0)}><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5 sm:col-span-2">`);
      Label($$renderer3, {
        for: "arrangement-name",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Arrangement name`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "arrangement-name",
        class: "min-h-11",
        maxlength: "190",
        placeholder: "Main hall checkerboard",
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
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "arrangement-rows",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Rows`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "arrangement-rows",
        type: "number",
        min: "1",
        max: "500",
        class: "min-h-11",
        required: true,
        get value() {
          return form.rows;
        },
        set value($$value) {
          form.rows = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.rows) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.rows)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="space-y-1.5">`);
      Label($$renderer3, {
        for: "arrangement-columns",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Columns`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: "arrangement-columns",
        type: "number",
        min: "1",
        max: "500",
        class: "min-h-11",
        required: true,
        get value() {
          return form.columns;
        },
        set value($$value) {
          form.columns = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (form.errors.columns) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.columns)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div> <fieldset class="space-y-3"><legend class="text-sm font-medium">Distribution pattern</legend> <div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
      const each_array = ensure_array_like(patternOptions());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let pattern = each_array[$$index];
        $$renderer3.push(`<label${attr_class(`border-border hover:bg-muted/50 flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors ${form.booklet_pattern === pattern.value ? "border-primary bg-primary/5 ring-primary ring-1" : ""}`)}><input type="radio" name="booklet_pattern"${attr("value", pattern.value)}${attr("checked", form.booklet_pattern === pattern.value, true)} class="accent-primary mt-0.5 size-4"/> <span><span class="block text-sm font-medium">${escape_html(pattern.label)}</span> `);
        if (pattern.description) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="text-muted-foreground mt-0.5 block text-xs">${escape_html(pattern.description)}</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></span></label>`);
      }
      $$renderer3.push(`<!--]--></div> `);
      if (form.errors.booklet_pattern) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.booklet_pattern)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></fieldset> <fieldset class="space-y-3"><legend class="text-sm font-medium">Room availability</legend> <div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
      const each_array_1 = ensure_array_like(availabilityOptions());
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let option = each_array_1[$$index_1];
        $$renderer3.push(`<label${attr_class(`border-border hover:bg-muted/50 flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors ${form.availability_mode === option.value ? "border-primary bg-primary/5 ring-primary ring-1" : ""}`)}><input type="radio" name="availability_mode"${attr("value", option.value)}${attr("checked", form.availability_mode === option.value, true)} class="accent-primary mt-0.5 size-4"/> <span><span class="block text-sm font-medium">${escape_html(option.label)}</span> `);
        if (option.description) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="text-muted-foreground mt-0.5 block text-xs">${escape_html(option.description)}</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></span></label>`);
      }
      $$renderer3.push(`<!--]--></div> `);
      if (form.errors.availability_mode) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.availability_mode)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></fieldset> `);
      if (form.availability_mode === "selected_rooms") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<fieldset class="space-y-3" aria-describedby="selected-room-help"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><legend class="text-sm font-medium">Selected rooms</legend> <p id="selected-room-help" class="text-muted-foreground mt-1 text-xs">Only these rooms can use this arrangement. Each selected room must hold all ${escape_html(deskCount())} desks.</p></div> `);
        if (compatibleActiveRooms().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex items-center gap-3 text-xs shrink-0"><button type="button" class="text-primary hover:underline font-medium cursor-pointer">${escape_html(allCompatibleRoomsSelected() ? "Deselect compatible" : `Select all compatible (${compatibleActiveRooms().length})`)}</button> `);
          if (selectedRoomIds().length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<button type="button" class="text-muted-foreground hover:text-destructive hover:underline cursor-pointer">Clear</button>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (activeRooms().length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border bg-muted/20 text-muted-foreground rounded-lg border border-dashed p-4 text-sm">No active rooms are available yet. Create or activate a room before restricting applicability.</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
          const each_array_2 = ensure_array_like(activeRooms());
          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
            let room = each_array_2[$$index_2];
            const incompatible = Number(room.capacity ?? 0) < deskCount();
            $$renderer3.push(`<label${attr_class(`border-border flex min-h-14 items-start gap-3 rounded-lg border px-4 py-3 ${incompatible ? "bg-amber-500/5" : "hover:bg-muted/50"}`)}><input type="checkbox"${attr("checked", selectedRoomIds().includes(String(room.id)), true)} class="accent-primary mt-0.5 size-4"${attr("aria-label", `Use ${roomLabel(room)}`)}/> <span class="min-w-0"><span class="block truncate text-sm font-medium">${escape_html(roomLabel(room))}</span> <span class="text-muted-foreground block text-xs">Capacity ${escape_html(room.capacity ?? "—")} `);
            if (incompatible) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="text-amber-700 dark:text-amber-300">· too small</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></span></span></label>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (form.errors.room_ids) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-destructive text-sm">${escape_html(form.errors.room_ids)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></fieldset>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="border-border bg-muted/20 rounded-lg border p-4" id="arrangement-form-status" aria-live="polite"><div class="flex items-start gap-3"><input id="arrangement-active" type="checkbox"${attr("checked", form.is_active, true)} class="accent-primary mt-0.5 size-4"/> <label for="arrangement-active"><span class="block text-sm font-medium">Active for scheduling</span> <span class="text-muted-foreground mt-0.5 block text-xs">Inactive arrangements stay available for history but cannot be selected for a new session.</span></label></div></div> `);
      if (form.availability_mode === "all_rooms" && incompatibleAllRooms().length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-100 rounded-lg border px-4 py-3 text-sm" role="status"><p class="font-medium">Some rooms are too small for this arrangement.</p> <p class="mt-1">It remains available to compatible rooms only. ${escape_html(incompatibleAllRooms().length)} active ${escape_html(incompatibleAllRooms().length === 1 ? "room is" : "rooms are")} below the ${escape_html(deskCount())}-desk capacity.</p></div>`);
      } else if (form.availability_mode === "selected_rooms" && incompatibleSelectedRooms().length > 0) {
        $$renderer3.push("<!--[1-->");
        $$renderer3.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm" role="alert"><p class="font-medium">Selected rooms cannot hold this arrangement.</p> <p class="mt-1">Choose different rooms or reduce the rows/columns before saving.</p></div>`);
      } else if (form.availability_mode === "selected_rooms" && missingSelectedRooms().length > 0) {
        $$renderer3.push("<!--[2-->");
        $$renderer3.push(`<div class="border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-100 rounded-lg border px-4 py-3 text-sm" role="status">Some previously selected rooms are no longer available. Review the selection before saving.</div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (availabilityError() || serverError()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm" role="alert">${escape_html(availabilityError() ?? serverError())}</div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (conflict || form.errors.lock_version || form.errors.conflict) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-amber-500/40 bg-amber-500/10 rounded-lg border px-4 py-3 text-sm" role="alert"><p class="font-medium">This arrangement changed in another session.</p> <p class="text-muted-foreground mt-1">Your save was not applied. Reload the current arrangement before making another edit.</p> `);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          size: "sm",
          class: "mt-3 min-h-10",
          onclick: () => router.reload(),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Reload current version`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (hasReferenceFacts()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-border bg-muted/20 rounded-lg border px-4 py-3 text-sm"><p class="font-medium">Published use and defaults</p> <p class="text-muted-foreground mt-1">${escape_html(published_facts?.count ?? published_facts?.sessions_count ?? 0)} published ${escape_html((published_facts?.count ?? published_facts?.sessions_count ?? 0) === 1 ? "session uses" : "sessions use")} this arrangement.
                    ${escape_html(referenced_facts?.default_rooms_count ?? referenced_facts?.rooms_count ?? 0)} room default${escape_html((referenced_facts?.default_rooms_count ?? referenced_facts?.rooms_count ?? 0) === 1 ? "" : "s")} reference it.</p></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-2 pt-1">`);
      Button($$renderer3, {
        type: "submit",
        disabled: form.processing || Boolean(availabilityError()) || incompatibleSelectedRooms().length > 0,
        class: "min-h-11",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(form.processing ? "Saving…" : editing ? "Save arrangement" : "Create arrangement")}`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: back_href,
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
      $$renderer3.push(`<!----></div></form> <div class="space-y-4 xl:sticky xl:top-24 xl:self-start">`);
      SeatingArrangementPreview($$renderer3, {
        rows: form.rows,
        columns: form.columns,
        booklet_pattern: form.booklet_pattern
      });
      $$renderer3.push(`<!----> <div class="border-border bg-card rounded-xl border p-5"><h2 class="font-semibold">Compatibility check</h2> <dl class="mt-3 grid gap-3 text-sm"><div class="flex items-center justify-between gap-4"><dt class="text-muted-foreground">Desk count</dt> <dd class="font-medium">${escape_html(deskCount())}</dd></div> <div class="flex items-center justify-between gap-4"><dt class="text-muted-foreground">Active rooms</dt> <dd class="font-medium">${escape_html(activeRooms().length)}</dd></div> <div class="flex items-center justify-between gap-4"><dt class="text-muted-foreground">Compatible rooms</dt> <dd class="font-medium">${escape_html(Math.max(0, activeRooms().length - incompatibleAllRooms().length))}</dd></div> `);
      if (form.availability_mode === "selected_rooms") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="flex items-center justify-between gap-4"><dt class="text-muted-foreground">Selected rooms</dt> <dd class="font-medium">${escape_html(selectedRoomIds().length)}</dd></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></dl> <p class="text-muted-foreground mt-4 text-xs leading-relaxed">Compatibility is checked again by the server when this arrangement is saved, selected as a room default, or used by an exam session.</p></div></div></div>`);
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
  SeatingArrangementForm as default
};
