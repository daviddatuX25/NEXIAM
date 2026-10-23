import { h as head, a as ensure_array_like, e as escape_html, x as store_get, c as attr_class, b as attr, B as Button, y as unsubscribe_stores, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { b as bookingUnlocked } from "./booking-visibility-DG8T2RPN.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
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
import "./PreferencesMenu-CCPeta-5.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./noop-BQS7OFs8.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
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
function ConsultationSessionEdit($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let { session, cycles = [], rooms = [], booking_modes = [] } = $$props;
    function formatForInput(val) {
      if (!val) return "";
      try {
        const d = new Date(val.includes("T") ? val : val.replace(" ", "T"));
        if (isNaN(d.getTime())) return val;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        const hours = String(d.getHours()).padStart(2, "0");
        const minutes = String(d.getMinutes()).padStart(2, "0");
        return `${year}-${month}-${day}T${hours}:${minutes}`;
      } catch {
        return val;
      }
    }
    const form = useForm({
      academic_cycle_id: String(session.academic_cycle?.id ?? session.academic_cycle_id ?? ""),
      room_id: String(session.room?.id ?? session.room_id ?? ""),
      code: session.code ?? "",
      scheduled_at: formatForInput(session.scheduled_at),
      booking_mode: session.booking_mode ?? "staff_only",
      expected_version: session.version ?? 1
    });
    const bookingModesList = derived(() => booking_modes && booking_modes.length > 0 ? booking_modes : [
      { value: "staff_only", label: "Staff-only (office assigns)" },
      {
        value: "open_for_reservation",
        label: "Self-booking (students reserve)"
      }
    ]);
    const breadcrumbs = [
      { label: "Consultation", href: "/consultation" },
      { label: "Session Scheduling", href: "/consultation/sessions" },
      {
        label: session.code ?? "Session",
        href: `/consultation/sessions/${session.id}`
      },
      { label: "Edit" }
    ];
    const selectedCycle = derived(() => cycles.find((cycle) => String(cycle.id) === String(form.academic_cycle_id)) ?? null);
    const selectedRoom = derived(() => rooms.find((room) => String(room.id) === String(form.room_id)) ?? null);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1g44xby", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Edit Consultation Session · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><div role="presentation">`);
          PageHeaderBar($$renderer4, {
            title: "Edit Consultation Session",
            description: "Update schedule details, venue assignments, and capacity for draft consultation sessions."
          });
          $$renderer4.push(`<!----></div> <form class="space-y-6"><div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start"><div class="border-border bg-card space-y-4 rounded-xl border p-6 shadow-xs"><div class="space-y-1.5">`);
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
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
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
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
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
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
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
                  $$renderer6.push(`${escape_html(room.label)}${escape_html(room.capacity ? ` (Capacity: ${room.capacity})` : "")}`);
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
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
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
            class: "min-h-10",
            placeholder: "e.g. CS-2026-AUG-A",
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
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "scheduled_at",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Scheduled date &amp; time`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "scheduled_at",
            type: "datetime-local",
            class: "min-h-10",
            required: true,
            get value() {
              return form.scheduled_at;
            },
            set value($$value) {
              form.scheduled_at = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.scheduled_at) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.scheduled_at)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked) || session.booking_mode === "open_for_reservation") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2">`);
            Label($$renderer4, {
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Booking mode <span class="text-destructive">*</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> <p class="text-muted-foreground text-xs">Choose whether staff assigns applicants or students
                        self-book.</p> <div class="space-y-2 pt-1"><!--[-->`);
            const each_array_2 = ensure_array_like(bookingModesList());
            for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
              let mode = each_array_2[$$index_2];
              $$renderer4.push(`<label${attr_class(`border-border bg-card hover:bg-muted/50 flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-4 py-2.5 transition-colors ${form.booking_mode === mode.value ? "border-primary bg-primary/5 ring-primary ring-1" : ""}`)}><input type="radio" name="booking_mode"${attr("value", mode.value)}${attr("checked", form.booking_mode === mode.value, true)} class="accent-primary size-4" required=""/> <span class="text-sm font-medium">${escape_html(mode.label)}</span></label>`);
            }
            $$renderer4.push(`<!--]--></div> `);
            if (form.errors.booking_mode) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.booking_mode)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors.schedule) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm">${escape_html(form.errors.schedule)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex gap-2 pt-2">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Saving..." : "Save changes")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: `/consultation/sessions/${session.id}`,
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                class: "min-h-10",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></div> <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h3 class="font-semibold text-sm text-foreground">Session Configuration</h3> <p class="text-xs text-muted-foreground">Live summary of session venue and schedule</p></div> <div class="space-y-3 text-sm"><div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Academic Year</span> <span class="font-medium text-foreground text-right">${escape_html(selectedCycle()?.label ?? "None selected")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Venue</span> <span class="font-medium text-foreground text-right">${escape_html(selectedRoom()?.label ?? selectedRoom()?.name ?? "None selected")}</span></div> `);
          if (selectedRoom()?.capacity) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Room Capacity</span> <span class="font-medium text-foreground text-right">${escape_html(selectedRoom().capacity)} seats</span></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Session Code</span> <span class="font-mono text-xs font-semibold text-foreground">${escape_html(form.code || "Pending entry")}</span></div> <div class="flex items-center justify-between py-1.5 border-b border-border/50"><span class="text-muted-foreground">Schedule</span> <span class="font-medium text-foreground text-right">${escape_html(form.scheduled_at ? new Date(form.scheduled_at).toLocaleString() : "Not scheduled")}</span></div></div> <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Consultation sessions enable counselors to review applicant exam results, discuss program suitability, and record official program endorsements.</div></div></div></form></div>`);
        }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    if ($$store_subs) unsubscribe_stores($$store_subs);
  });
}
export {
  ConsultationSessionEdit as default
};
