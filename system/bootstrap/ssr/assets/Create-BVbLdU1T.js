import { h as head, a as ensure_array_like, e as escape_html, b as attr, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import SeatingArrangementPreview from "./SeatingArrangementPreview-BlLfpBBH.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { B as Bell } from "./PreferencesMenu-CCPeta-5.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      cycles = [],
      rooms = [],
      booklets = [],
      seating_arrangements = [],
      arrangement_preview = [],
      default_cycle_id = null,
      cycle_exam_notification_policy = null,
      enable_seating_arrangements = false
    } = $$props;
    const defaultDate = /* @__PURE__ */ new Date();
    defaultDate.setDate(defaultDate.getDate() + 1);
    const formattedDefaultDate = `${defaultDate.getFullYear()}-${String(defaultDate.getMonth() + 1).padStart(2, "0")}-${String(defaultDate.getDate()).padStart(2, "0")}`;
    const form = useForm({
      academic_cycle_id: default_cycle_id ? String(default_cycle_id) : "",
      room_id: "",
      code: "Batch-01",
      date: formattedDefaultDate,
      start_time: "08:00",
      end_time: "09:30",
      seating_mode: "simple_capacity",
      seating_arrangement_id: "",
      use_cycle_notification_defaults: true,
      notification_policy: {
        publication_email_enabled: cycle_exam_notification_policy?.publication_email_enabled ?? true,
        reminder_offsets_minutes: cycle_exam_notification_policy?.reminder_offsets_minutes ?? [4320, 1440, 120, 60],
        reminder_email_enabled: cycle_exam_notification_policy?.reminder_email_enabled ?? true
      }
    });
    let customizeNotifications = false;
    form.room_id;
    const selectedRoom = derived(() => rooms.find((room) => String(room.id) === String(form.room_id)) ?? null);
    const selectedBooklet = derived(() => booklets.find((booklet) => String(booklet.id) === String(form.booklet_version_id)) ?? null);
    const compatibleArrangements = derived(() => {
      const roomId = String(form.room_id || "");
      return seating_arrangements.filter((arrangement) => {
        if (arrangement.is_active === false || arrangement.is_compatible === false) return false;
        if (!roomId) return true;
        if (arrangement.room_id && String(arrangement.room_id) !== roomId) return false;
        const roomIds = arrangement.room_ids ?? (arrangement.rooms ?? []).map((room) => typeof room === "object" ? room.id : room);
        if (arrangement.availability_mode === "selected_rooms" && Array.isArray(roomIds) && roomIds.length > 0 && !roomIds.map(String).includes(roomId)) return false;
        return true;
      });
    });
    const incompatibleArrangements = derived(() => seating_arrangements.filter((arrangement) => arrangement.is_active !== false && arrangement.is_compatible === false));
    const selectedArrangement = derived(() => seating_arrangements.find((arrangement) => String(arrangement.id) === String(form.seating_arrangement_id)) ?? null);
    const previewDesks = derived(() => {
      if (selectedArrangement()?.preview && Array.isArray(selectedArrangement().preview)) return selectedArrangement().preview;
      if (selectedArrangement()?.desks && Array.isArray(selectedArrangement().desks)) return selectedArrangement().desks;
      if (Array.isArray(arrangement_preview)) return arrangement_preview;
      if (arrangement_preview && selectedArrangement()?.id) return arrangement_preview[selectedArrangement().id] ?? [];
      return [];
    });
    const arrangementError = derived(() => form.errors.seating_arrangement_id || form.errors.arrangement || form.errors.material || null);
    function roomLabel(room) {
      return room.label ?? room.name ?? room.code ?? `Room ${room.id}`;
    }
    function arrangementLabel(arrangement) {
      return arrangement.label ?? arrangement.name ?? `Arrangement ${arrangement.id}`;
    }
    const breadcrumbs = [
      { label: "Exam Scheduling", href: "/exams/sessions" },
      { label: "Create" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("tr1n2f", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Create Exam Session</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Create Exam Session",
            description: "Schedule by room headcount, then opt into physical seating only when this session needs it."
          });
          $$renderer4.push(`<!----> <form class="space-y-6"><div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)]"><div class="border-border bg-card min-w-0 space-y-5 rounded-xl border p-5 sm:p-6"><div class="grid gap-4 sm:grid-cols-2"><div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "academic_cycle_id",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Academic year`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "academic_cycle_id",
              class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm",
              value: form.academic_cycle_id,
              required: true
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "" }, ($$renderer6) => {
                $$renderer6.push(`Select academic year`);
              });
              $$renderer5.push(`<!--[-->`);
              const each_array = ensure_array_like(cycles);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let cycle = each_array[$$index];
                $$renderer5.option({ value: String(cycle.id) }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(cycle.label)}${escape_html(cycle.is_active ? " (Active)" : "")}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(` `);
          if (form.errors.academic_cycle_id) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.academic_cycle_id)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "room_id",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Room`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "room_id",
              class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm",
              value: form.room_id,
              required: true
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "" }, ($$renderer6) => {
                $$renderer6.push(`Select room`);
              });
              $$renderer5.push(`<!--[-->`);
              const each_array_1 = ensure_array_like(rooms);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let room = each_array_1[$$index_1];
                $$renderer5.option({ value: String(room.id) }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(roomLabel(room))}${escape_html(room.capacity ? ` · capacity ${room.capacity}` : "")}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(` `);
          if (form.errors.room_id) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.room_id)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (enable_seating_arrangements) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="sm:col-span-2 space-y-2"><div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"><div class="min-w-0 space-y-1"><p class="text-sm font-medium">Seating mode</p> <p id="assigned-seating-availability-help" class="text-muted-foreground text-xs">New sessions start with room capacity. Choose physical seats only when this session needs them.</p></div> <label for="assign_physical_seats" class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2 text-sm font-medium"><input id="assign_physical_seats" type="checkbox" role="switch"${attr("checked", form.seating_mode === "assigned_seating", true)}${attr("aria-checked", form.seating_mode === "assigned_seating")} aria-describedby="assigned-seating-availability-help" class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"/> <span>Assign physical seats</span></label></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors.seating_mode) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm sm:col-span-2">${escape_html(form.errors.seating_mode)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (enable_seating_arrangements && form.seating_mode === "assigned_seating") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-1.5 sm:col-span-2">`);
            Label($$renderer4, {
              for: "seating_arrangement_id",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Seating arrangement`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "seating_arrangement_id",
                class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm",
                value: form.seating_arrangement_id
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`Select arrangement`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_2 = ensure_array_like(compatibleArrangements());
                for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                  let arrangement = each_array_2[$$index_2];
                  $$renderer5.option({ value: String(arrangement.id) }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(arrangementLabel(arrangement))} · ${escape_html(arrangement.desk_count ?? (arrangement.rows ?? 0) * (arrangement.columns ?? 0))} desks`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(` `);
            if (!form.room_id) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-muted-foreground text-xs">Select a room first to check applicability and capacity.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (form.room_id && compatibleArrangements().length === 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive text-sm">No active seating arrangement is applicable to this room.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (arrangementError()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(arrangementError())}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "booklet_version_id",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Finalized Booklet`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "booklet_version_id",
              class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm",
              value: form.booklet_version_id,
              required: true
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "" }, ($$renderer6) => {
                $$renderer6.push(`Select finalized Booklet`);
              });
              $$renderer5.push(`<!--[-->`);
              const each_array_3 = ensure_array_like(booklets);
              for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                let booklet = each_array_3[$$index_3];
                $$renderer5.option({ value: String(booklet.id) }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(booklet.label)} · ${escape_html(booklet.answer_sheet)}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(` `);
          if (selectedBooklet()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground text-xs">Inherited Answer Sheet: <span class="text-foreground font-medium">${escape_html(selectedBooklet().answer_sheet)}</span></p>`);
          } else if (booklets.length === 0) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<p class="text-destructive text-sm">Finalize a Booklet in Exam materials before
                                    scheduling this session.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors.booklet_version_id) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.booklet_version_id)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "code",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Session code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "code",
            class: "min-h-11",
            placeholder: "e.g. EX-2026-FRI-A",
            required: true,
            get value() {
              return form.code;
            },
            set value($$value) {
              form.code = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.code) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.code)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "date",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Date`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "date",
            type: "date",
            class: "min-h-11",
            required: true,
            get value() {
              return form.date;
            },
            set value($$value) {
              form.date = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.starts_at) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.starts_at)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "start_time",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Start time`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "start_time",
            type: "time",
            class: "min-h-11",
            required: true,
            get value() {
              return form.start_time;
            },
            set value($$value) {
              form.start_time = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "end_time",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->End time`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "end_time",
            type: "time",
            class: "min-h-11",
            required: true,
            get value() {
              return form.end_time;
            },
            set value($$value) {
              form.end_time = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.effective_end_at) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.effective_end_at)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></div> `);
          if (form.errors.schedule) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.schedule)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors.material) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.material)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="border-border bg-muted/20 min-w-0 space-y-4 rounded-xl border p-4 sm:p-5"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3"><div class="space-y-0.5"><h3 class="text-sm font-semibold flex items-center gap-2">`);
          Bell($$renderer4, { class: "size-4 text-primary" });
          $$renderer4.push(`<!----> Schedule Notifications &amp; Reminders</h3> <p class="text-xs text-muted-foreground">Configure applicant communication for this exam session.</p></div> <label class="flex items-center gap-2 cursor-pointer text-xs font-medium"><input type="checkbox"${attr("checked", customizeNotifications, true)} class="size-4 rounded border-border accent-primary"/> Customize for this session</label></div> `);
          {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-lg bg-muted/40 p-3.5 text-xs space-y-1.5 border border-border/50"><p class="font-medium text-foreground">Using Academic Year Defaults:</p> <ul class="list-disc list-inside text-muted-foreground space-y-0.5"><li>Email on publish: ${escape_html(cycle_exam_notification_policy?.publication_email_enabled ?? true ? "Enabled" : "Disabled")}</li> <li>Reminders: ${escape_html((cycle_exam_notification_policy?.reminder_offsets_minutes ?? [4320, 1440, 120, 60]).map((m) => m === 4320 ? "72h" : m === 1440 ? "24h" : m === 120 ? "2h" : "1h").join(", "))} before</li> <li>Reminder email copy: ${escape_html(cycle_exam_notification_policy?.reminder_email_enabled ?? true ? "Enabled" : "Disabled")}</li></ul></div>`);
          }
          $$renderer4.push(`<!--]--></div></div> <div class="space-y-4 xl:sticky xl:top-24 xl:self-start">`);
          if (form.seating_mode === "simple_capacity") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border bg-card rounded-xl border p-5 text-sm"><p class="font-medium">${escape_html(selectedRoom() ? roomLabel(selectedRoom()) : "Session summary")}</p> <p class="text-muted-foreground mt-1">${escape_html(selectedRoom() ? `Room capacity: ${selectedRoom().capacity ?? "—"} seats` : "Select a room to view capacity.")}</p> <p class="text-muted-foreground mt-2 text-xs">At publication, this session uses Set A and the room's full headcount capacity. No physical seat map is created.</p></div>`);
          } else if (form.seating_mode === "assigned_seating" && selectedArrangement()) {
            $$renderer4.push("<!--[1-->");
            SeatingArrangementPreview($$renderer4, {
              rows: selectedArrangement().rows,
              columns: selectedArrangement().columns,
              booklet_pattern: selectedArrangement().booklet_pattern,
              desks: previewDesks(),
              compact: true,
              title: "Session seating preview"
            });
            $$renderer4.push(`<!----> <div class="border-border bg-card rounded-xl border p-5 text-sm"><p class="font-medium">${escape_html(arrangementLabel(selectedArrangement()))}</p> <p class="text-muted-foreground mt-1">${escape_html(selectedArrangement().rows)} rows × ${escape_html(selectedArrangement().columns)} columns · ${escape_html(selectedArrangement().desk_count ?? (selectedArrangement().rows ?? 0) * (selectedArrangement().columns ?? 0))} desks</p> `);
            if (selectedRoom()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-muted-foreground mt-2">Room capacity: ${escape_html(selectedRoom().capacity ?? "—")}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <p class="text-muted-foreground mt-2 text-xs">This physical layout is frozen into the session only when it is published.</p></div>`);
          } else if (form.seating_mode === "assigned_seating") {
            $$renderer4.push("<!--[2-->");
            $$renderer4.push(`<div class="border-border bg-card text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm" role="status">Choose a room and compatible seating arrangement to preview the physical seats.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (enable_seating_arrangements && form.seating_mode === "assigned_seating" && incompatibleArrangements().length > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm" role="status">${escape_html(incompatibleArrangements().length)} active ${escape_html(incompatibleArrangements().length === 1 ? "arrangement is" : "arrangements are")} incompatible with the selected
                            room and hidden from this list.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></div> <div class="flex flex-wrap gap-2">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-11",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Creating…" : "Create session")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: "/exams/sessions",
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                class: "min-h-11",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form></div>`);
        }
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
  Create as default
};
