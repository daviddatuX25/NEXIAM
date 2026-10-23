import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, x as store_get, f as attr_style, s as stringify, B as Button, a as ensure_array_like, b as attr, y as unsubscribe_stores, d as derived } from "../app.js";
import { S as StaffLayout, a as Send } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { b as bookingUnlocked } from "./booking-visibility-DG8T2RPN.js";
import { A as Arrow_right_left } from "./arrow-right-left-Cx5FZkqL.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as External_link } from "./external-link-DmB8ndP3.js";
import { U as User_minus } from "./user-minus-CPyEYZhK.js";
import { U as User_plus } from "./user-plus-CCVf9WDs.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { B as Ban } from "./ban-CUQ_jw2o.js";
import { U as Undo_2 } from "./undo-2-CzBOZ6l_.js";
import { C as Circle_check_big } from "./ToastManager-C7oO_4Jn.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
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
import "./triangle-alert-CUvuB-HL.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
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
import "./x-CnxrMbMn.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./clone-BJz-lxsY.js";
import "./check-DK45Y9SJ.js";
import "./minus-Dqkhcxt7.js";
import "./circle-x-BQrhluGu.js";
import "./info-C3MCzlQW.js";
function Calendar_check($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M8 2v4" }],
    ["path", { "d": "M16 2v4" }],
    [
      "rect",
      { "width": "18", "height": "18", "x": "3", "y": "4", "rx": "2" }
    ],
    ["path", { "d": "M3 10h18" }],
    ["path", { "d": "m9 16 2 2 4-4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "calendar-check" },
    $$sanitized_props,
    {
      /**
       * @component @name CalendarCheck
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNOCAydjQiIC8+CiAgPHBhdGggZD0iTTE2IDJ2NCIgLz4KICA8cmVjdCB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHg9IjMiIHk9IjQiIHJ4PSIyIiAvPgogIDxwYXRoIGQ9Ik0zIDEwaDE4IiAvPgogIDxwYXRoIGQ9Im05IDE2IDIgMiA0LTQiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/calendar-check
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
function ConsultationSessionShow($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let {
      session,
      assigned_applicants = [],
      ready_pool = [],
      available_applicants = [],
      reassign_targets = [],
      publish_block_reason = null,
      is_publishable = false,
      is_completable = false,
      can_decide = false,
      errors = {}
    } = $$props;
    let selectedReady = [];
    let searchReady = "";
    let searchAssigned = "";
    let reasonOpen = false;
    let reasonMode = "unpublish";
    let reason = "";
    let selectedAssigned = [];
    let reassignOpen = false;
    let reassignTargetId = "";
    let reassignReason = "";
    let reassigning = false;
    const readyList = derived(() => ready_pool.length > 0 ? ready_pool : available_applicants);
    const filteredReady = derived(() => readyList().filter((app) => {
      const term = searchReady.toLowerCase();
      return app.name && app.name.toLowerCase().includes(term) || app.reference_number && app.reference_number.toLowerCase().includes(term) || app.email && app.email.toLowerCase().includes(term);
    }));
    const filteredAssigned = derived(() => assigned_applicants.filter((app) => {
      const term = searchAssigned.toLowerCase();
      return app.name && app.name.toLowerCase().includes(term) || app.reference_number && app.reference_number.toLowerCase().includes(term);
    }));
    const isAllAssignedSelected = derived(() => filteredAssigned().length > 0 && filteredAssigned().every((r) => selectedAssigned.includes(r.id)));
    const isSomeAssignedSelected = derived(() => filteredAssigned().some((r) => selectedAssigned.includes(r.id)) && !isAllAssignedSelected());
    const isAllReadySelected = derived(() => filteredReady().length > 0 && filteredReady().every((app) => selectedReady.includes(app.id)));
    const isSomeReadySelected = derived(() => filteredReady().some((app) => selectedReady.includes(app.id)) && !isAllReadySelected());
    function toggleAllAssigned(checked) {
      if (checked) {
        const newIds = filteredAssigned().map((r) => r.id).filter((id) => !selectedAssigned.includes(id));
        selectedAssigned = [...selectedAssigned, ...newIds];
      } else {
        const visibleIds = filteredAssigned().map((r) => r.id);
        selectedAssigned = selectedAssigned.filter((id) => !visibleIds.includes(id));
      }
    }
    function toggleAllReady(checked) {
      if (checked) {
        const newIds = filteredReady().map((a) => a.id).filter((id) => !selectedReady.includes(id));
        selectedReady = [...selectedReady, ...newIds];
      } else {
        const visibleIds = filteredReady().map((a) => a.id);
        selectedReady = selectedReady.filter((id) => !visibleIds.includes(id));
      }
    }
    function assignSingle(attemptId) {
      router.post(`/consultation/sessions/${session.id}/assign`, { admission_attempt_id: attemptId }, { preserveScroll: true });
    }
    function assignSelected() {
      if (selectedReady.length === 0) return;
      const ids = [...selectedReady];
      selectedReady = [];
      const postNext = (index) => {
        if (index >= ids.length) {
          router.reload();
          return;
        }
        router.post(`/consultation/sessions/${session.id}/assign`, { admission_attempt_id: ids[index] }, { preserveScroll: true, onFinish: () => postNext(index + 1) });
      };
      postNext(0);
    }
    function cancelAssignment(assignmentId) {
      router.post(`/consultation/sessions/${session.id}/assignments/${assignmentId}/cancel`, { reason: "Cancelled from session show" }, { preserveScroll: true });
    }
    function openReassign(ids) {
      selectedAssigned = [...ids];
      reassignTargetId = "";
      reassignReason = "";
      reassignOpen = true;
    }
    function submitReassign() {
      if (!reassignTargetId || selectedAssigned.length === 0) return;
      reassigning = true;
      router.post(
        `/consultation/sessions/${session.id}/reassign`,
        {
          assignment_ids: selectedAssigned,
          target_session_id: Number(reassignTargetId),
          reason: reassignReason || "Reassigned from session show"
        },
        {
          preserveScroll: true,
          onFinish: () => {
            reassigning = false;
            reassignOpen = false;
            selectedAssigned = [];
          }
        }
      );
    }
    const openReassignTargets = derived(() => reassign_targets.filter((t) => !t.full && (t.remaining === void 0 || t.remaining > 0)));
    function publish() {
      router.post(`/consultation/sessions/${session.id}/publish`, { expected_version: session.version });
    }
    function complete() {
      router.post(`/consultation/sessions/${session.id}/complete`, { expected_version: session.version });
    }
    function openReservation() {
      router.post(`/consultation/sessions/${session.id}/open-reservation`, { expected_version: session.version });
    }
    function closeReservation() {
      router.post(`/consultation/sessions/${session.id}/close-reservation`, { expected_version: session.version });
    }
    function openReason(mode) {
      reasonMode = mode;
      reason = "";
      reasonOpen = true;
    }
    function submitReason() {
      if (!reason.trim()) return;
      const path = reasonMode === "unpublish" ? `/consultation/sessions/${session.id}/unpublish` : `/consultation/sessions/${session.id}/cancel`;
      router.post(path, { expected_version: session.version, reason: reason.trim() }, {
        onFinish: () => {
          reasonOpen = false;
        }
      });
    }
    function statusVariant(status) {
      if (status === "draft") return "secondary";
      if (status === "published") return "default";
      if (status === "in_progress") return "outline";
      if (status === "completed") return "outline";
      if (status === "cancelled") return "destructive";
      return "outline";
    }
    function statusLabel(status) {
      if (status === "draft") return "Draft";
      if (status === "published") return "Published";
      if (status === "in_progress") return "In progress";
      if (status === "completed") return "Completed";
      if (status === "cancelled") return "Cancelled";
      return status ?? "—";
    }
    function bookingModeLabel(mode) {
      if (mode === "open_for_reservation") return "Self-booking (students reserve)";
      if (mode === "staff_only") return "Staff-only (office assigns)";
      return mode ?? "—";
    }
    function sourceLabel(source) {
      if (source === "applicant_reserved") return "Applicant reserved";
      if (source === "staff_assigned") return "Office assigned";
      return source ?? "—";
    }
    function formatScheduledAt(scheduledAt) {
      if (!scheduledAt) return "—";
      try {
        const d = new Date(scheduledAt.includes("T") ? scheduledAt : scheduledAt.replace(" ", "T"));
        if (!isNaN(d.getTime())) {
          return d.toLocaleDateString(void 0, {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit"
          });
        }
      } catch {
      }
      return scheduledAt;
    }
    const assignedCount = derived(() => session.assigned_count ?? assigned_applicants.length);
    const roomCapacity = derived(() => session.room?.capacity ?? null);
    const capacityPercent = derived(() => roomCapacity() && roomCapacity() > 0 ? Math.min(100, Math.round(assignedCount() / roomCapacity() * 100)) : 0);
    const actionError = derived(() => errors?.assign || errors?.reassign || errors?.cancel_assignment || errors?.remove || errors?.publish || errors?.complete || errors?.cancel || errors?.open_reservation || errors?.close_reservation || errors?.unpublish || null);
    const breadcrumbs = [
      { label: "Consultation & Counselling", href: "/consultation" },
      { label: "Session Scheduling", href: "/consultation/sessions" },
      { label: session?.code ?? "Session" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("12kh4u5", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(session?.code ?? "Consultation session")}</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><div role="presentation">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap gap-2">`);
              if (can_decide) {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: "/consultation",
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      variant: "outline",
                      class: "min-h-10 gap-2",
                      children: ($$renderer7) => {
                        Calendar_check($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Consultation decisions`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (session.status === "draft") {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: `/consultation/sessions/${session.id}/edit`,
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      variant: "outline",
                      class: "min-h-10 gap-2",
                      children: ($$renderer7) => {
                        Pencil($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Edit session`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  class: "min-h-10 gap-2",
                  disabled: !is_publishable,
                  title: publish_block_reason || "Publish session",
                  onclick: publish,
                  children: ($$renderer6) => {
                    Send($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Publish`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  variant: "outline",
                  class: "text-destructive min-h-10 gap-2",
                  onclick: () => openReason("cancel"),
                  children: ($$renderer6) => {
                    Ban($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Cancel session`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!---->`);
              } else if (session.status === "published") {
                $$renderer5.push("<!--[1-->");
                Button($$renderer5, {
                  variant: "outline",
                  class: "min-h-10 gap-2",
                  onclick: () => openReason("unpublish"),
                  children: ($$renderer6) => {
                    Undo_2($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Unpublish`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  variant: "outline",
                  class: "text-destructive min-h-10 gap-2",
                  onclick: () => openReason("cancel"),
                  children: ($$renderer6) => {
                    Ban($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Cancel session`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!---->`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (session.status === "published" || session.status === "in_progress" || is_completable) {
                $$renderer5.push("<!--[0-->");
                Button($$renderer5, {
                  variant: "outline",
                  class: "min-h-10 gap-2",
                  onclick: complete,
                  children: ($$renderer6) => {
                    Circle_check_big($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Complete session`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (session.status !== "completed" && session.status !== "cancelled") {
                $$renderer5.push("<!--[0-->");
                if (session.booking_mode === "open_for_reservation") {
                  $$renderer5.push("<!--[0-->");
                  Button($$renderer5, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    onclick: closeReservation,
                    children: ($$renderer6) => {
                      Lock($$renderer6, { class: "size-4" });
                      $$renderer6.push(`<!----> Close reservation`);
                    },
                    $$slots: { default: true }
                  });
                } else if (store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) {
                  $$renderer5.push("<!--[1-->");
                  Button($$renderer5, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    onclick: openReservation,
                    children: ($$renderer6) => {
                      Lock_open($$renderer6, { class: "size-4" });
                      $$renderer6.push(`<!----> Open reservation`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]-->`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: session.code,
              description: "View session details, assign applicants, and publish the schedule.",
              actions
            });
          }
          $$renderer4.push(`<!----></div> `);
          if (publish_block_reason && session.status === "draft") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">Cannot publish yet: ${escape_html(publish_block_reason)}</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (actionError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">${escape_html(actionError())}</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="border-border bg-card rounded-xl border p-6"><dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><div><dt class="text-muted-foreground text-sm">When</dt> <dd class="font-medium">${escape_html(formatScheduledAt(session.scheduled_at))}</dd></div> <div><dt class="text-muted-foreground text-sm">Room</dt> <dd class="font-medium">${escape_html(session.room?.name ?? session.room?.code ?? "—")} (cap. ${escape_html(session.room?.capacity ?? "—")})</dd></div> <div><dt class="text-muted-foreground text-sm">Academic year</dt> <dd class="font-medium">${escape_html(session.academic_cycle?.name ?? (session.academic_cycle?.school_year ? `${session.academic_cycle.school_year} ${session.academic_cycle.semester ?? ""}` : "—"))}</dd></div> <div><dt class="text-muted-foreground text-sm">Status</dt> <dd>`);
          Badge($$renderer4, {
            variant: statusVariant(session.status),
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(statusLabel(session.status))}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></dd></div> `);
          if (session.booking_mode === "open_for_reservation" || store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div><dt class="text-muted-foreground text-sm">Booking mode</dt> <dd>`);
            Badge($$renderer4, {
              variant: session.booking_mode === "open_for_reservation" ? "default" : "outline",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(bookingModeLabel(session.booking_mode))}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></dd></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div><dt class="text-muted-foreground text-sm">Capacity usage</dt> <dd class="font-medium">${escape_html(assignedCount())}`);
          if (roomCapacity()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`/ ${escape_html(roomCapacity())} (${escape_html(capacityPercent())}%)`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (roomCapacity()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="bg-muted mt-1.5 h-2 w-full max-w-[180px] overflow-hidden rounded-full"><div class="bg-primary h-full transition-all duration-300"${attr_style(`width: ${stringify(capacityPercent())}%`)}></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></dd></div></dl></div> <div class="border-border bg-card rounded-xl border p-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-lg font-semibold tracking-tight">Assigned applicants</h2> <p class="text-muted-foreground mt-1 text-sm">Cancel frees the seat. Reassign moves them to another
                        session in one step.</p></div> `);
          if (session.status !== "completed" && session.status !== "cancelled") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
            if (selectedAssigned.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-xs text-muted-foreground">Selected ${escape_html(selectedAssigned.length)} applicant(s)</span> `);
              Button($$renderer4, {
                variant: "ghost",
                size: "sm",
                class: "h-8 px-2 text-xs text-destructive hover:bg-destructive/10",
                onclick: () => selectedAssigned = [],
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Clear selection`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            Button($$renderer4, {
              variant: "outline",
              class: "min-h-10 gap-2 font-mono text-xs",
              disabled: selectedAssigned.length === 0 || openReassignTargets().length === 0,
              onclick: () => openReassign(selectedAssigned),
              title: openReassignTargets().length === 0 ? "No other sessions with capacity" : "Reassign selected",
              children: ($$renderer5) => {
                Arrow_right_left($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Reassign selected (${escape_html(selectedAssigned.length)})`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (assigned_applicants.length > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="relative mt-4 max-w-sm">`);
            Search($$renderer4, {
              class: "text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              type: "search",
              placeholder: "Search assigned...",
              class: "h-9 pl-8",
              get value() {
                return searchAssigned;
              },
              set value($$value) {
                searchAssigned = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> <div class="mt-4 overflow-x-auto">`);
            if (Table) {
              $$renderer4.push("<!--[-->");
              Table($$renderer4, {
                children: ($$renderer5) => {
                  if (Table_header) {
                    $$renderer5.push("<!--[-->");
                    Table_header($$renderer5, {
                      class: "bg-muted/50",
                      children: ($$renderer6) => {
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  class: "w-12",
                                  children: ($$renderer8) => {
                                    if (session.status !== "completed" && session.status !== "cancelled" && filteredAssigned().length > 0) {
                                      $$renderer8.push("<!--[0-->");
                                      Checkbox($$renderer8, {
                                        checked: isAllAssignedSelected(),
                                        indeterminate: isSomeAssignedSelected(),
                                        onCheckedChange: (checked) => toggleAllAssigned(Boolean(checked)),
                                        "aria-label": "Select all assigned applicants in this view"
                                      });
                                    } else {
                                      $$renderer8.push("<!--[-1-->");
                                    }
                                    $$renderer8.push(`<!--]-->`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              $$renderer7.push(` `);
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Reference`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              $$renderer7.push(` `);
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Name`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              $$renderer7.push(` `);
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Source`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              $$renderer7.push(` `);
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Status`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              $$renderer7.push(` `);
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  class: "text-center",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Actions`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Table_body) {
                    $$renderer5.push("<!--[-->");
                    Table_body($$renderer5, {
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!--[-->`);
                        const each_array = ensure_array_like(filteredAssigned());
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let row = each_array[$$index];
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (session.status !== "completed" && session.status !== "cancelled") {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<input type="checkbox" class="accent-primary size-4"${attr("checked", selectedAssigned.includes(row.id), true)}${attr("aria-label", `Select ${row.name}`)}/>`);
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                      }
                                      $$renderer8.push(`<!--]-->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    class: "font-medium",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(row.reference_number)}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(row.name)}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      Badge($$renderer8, {
                                        variant: row.source === "applicant_reserved" ? "default" : "secondary",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(sourceLabel(row.source))}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    class: "text-muted-foreground",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(row.status ?? "assigned")}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    class: "text-center",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<div class="flex flex-wrap items-center justify-center gap-1">`);
                                      if (row.admission_attempt_id || row.attempt_id || row.attempt?.id) {
                                        $$renderer8.push("<!--[0-->");
                                        Link($$renderer8, {
                                          href: `/consultation/applicants/${row.admission_attempt_id || row.attempt_id || row.attempt?.id}?session_id=${session.id}`,
                                          children: ($$renderer9) => {
                                            Button($$renderer9, {
                                              variant: "ghost",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs",
                                              children: ($$renderer10) => {
                                                External_link($$renderer10, { class: "mr-1.5 size-3.5" });
                                                $$renderer10.push(`<!----> Advisory Workspace`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          },
                                          $$slots: { default: true }
                                        });
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                      }
                                      $$renderer8.push(`<!--]--> `);
                                      if (session.status !== "completed" && session.status !== "cancelled") {
                                        $$renderer8.push("<!--[0-->");
                                        Button($$renderer8, {
                                          variant: "ghost",
                                          size: "sm",
                                          class: "h-8 px-2 text-xs",
                                          disabled: openReassignTargets().length === 0,
                                          onclick: () => openReassign([row.id]),
                                          children: ($$renderer9) => {
                                            Arrow_right_left($$renderer9, { class: "mr-1.5 size-3.5" });
                                            $$renderer9.push(`<!----> Reassign`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!----> `);
                                        Button($$renderer8, {
                                          variant: "ghost",
                                          size: "sm",
                                          class: "text-destructive h-8 px-2 text-xs",
                                          onclick: () => cancelAssignment(row.id),
                                          children: ($$renderer9) => {
                                            User_minus($$renderer9, { class: "mr-1.5 size-3.5" });
                                            $$renderer9.push(`<!----> Cancel`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!---->`);
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                      }
                                      $$renderer8.push(`<!--]--></div>`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
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
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(`</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<p class="text-muted-foreground mt-4 text-sm">No applicants assigned yet.</p>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (session.status !== "completed" && session.status !== "cancelled") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border bg-card rounded-xl border p-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-semibold tracking-tight">Ready pool (available applicants)</h2> <p class="text-muted-foreground mt-1 text-sm">Accepted applicants with finalized results and
                            prepared documents, without an active consultation
                            assignment.</p></div> <div class="flex flex-wrap items-center gap-2">`);
            if (selectedReady.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-xs text-muted-foreground">Selected ${escape_html(selectedReady.length)} applicant(s)</span> `);
              Button($$renderer4, {
                variant: "ghost",
                size: "sm",
                class: "h-8 px-2 text-xs text-destructive hover:bg-destructive/10",
                onclick: () => selectedReady = [],
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Clear selection`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            Button($$renderer4, {
              class: "min-h-10 gap-2 font-mono text-xs",
              disabled: selectedReady.length === 0,
              onclick: assignSelected,
              children: ($$renderer5) => {
                User_plus($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Assign selected (${escape_html(selectedReady.length)})`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></div> `);
            if (readyList().length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="relative mt-4 max-w-sm">`);
              Search($$renderer4, {
                class: "text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                type: "search",
                placeholder: "Search ready pool...",
                class: "h-9 pl-8",
                get value() {
                  return searchReady;
                },
                set value($$value) {
                  searchReady = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----></div> <div class="mt-4 overflow-x-auto">`);
              if (Table) {
                $$renderer4.push("<!--[-->");
                Table($$renderer4, {
                  children: ($$renderer5) => {
                    if (Table_header) {
                      $$renderer5.push("<!--[-->");
                      Table_header($$renderer5, {
                        class: "bg-muted/50",
                        children: ($$renderer6) => {
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    class: "w-12",
                                    children: ($$renderer8) => {
                                      if (filteredReady().length > 0) {
                                        $$renderer8.push("<!--[0-->");
                                        Checkbox($$renderer8, {
                                          checked: isAllReadySelected(),
                                          indeterminate: isSomeReadySelected(),
                                          onCheckedChange: (checked) => toggleAllReady(Boolean(checked)),
                                          "aria-label": "Select all ready applicants in this view"
                                        });
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                      }
                                      $$renderer8.push(`<!--]-->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Reference`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Name`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Email`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    class: "text-center",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Action`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Table_body) {
                      $$renderer5.push("<!--[-->");
                      Table_body($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!--[-->`);
                          const each_array_1 = ensure_array_like(filteredReady());
                          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                            let app = each_array_1[$$index_1];
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<input type="checkbox" class="accent-primary size-4"${attr("checked", selectedReady.includes(app.id), true)}${attr("aria-label", `Select ${app.name}`)}/>`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "font-medium",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(app.reference_number)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(app.name)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "text-muted-foreground",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(app.email ?? "—")}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "text-center",
                                      children: ($$renderer8) => {
                                        Button($$renderer8, {
                                          variant: "ghost",
                                          size: "sm",
                                          class: "h-8 px-2 text-xs font-semibold",
                                          onclick: () => assignSingle(app.id),
                                          children: ($$renderer9) => {
                                            User_plus($$renderer9, { class: "mr-1.5 size-3.5" });
                                            $$renderer9.push(`<!----> Assign`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
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
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
              $$renderer4.push(`</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="text-muted-foreground mt-4 text-sm">No eligible applicants in ready pool.</p>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return reasonOpen;
              },
              set open($$value) {
                reasonOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-md",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(reasonMode === "unpublish" ? "Unpublish consultation session" : "Cancel consultation session")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(reasonMode === "unpublish" ? "Returns the session to draft. Assignments stay; applicants may need a new publish notice later." : "Terminal cancel. Frees active assignments. Requires a reason.")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      Textarea($$renderer6, {
                        rows: 3,
                        placeholder: "Reason…",
                        get value() {
                          return reason;
                        },
                        set value($$value) {
                          reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => reasonOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: reasonMode === "cancel" ? "destructive" : "default",
                              disabled: !reason.trim(),
                              onclick: submitReason,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Confirm`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!---->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return reassignOpen;
              },
              set open($$value) {
                reassignOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-md",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Reassign applicant${escape_html(selectedAssigned.length === 1 ? "" : "s")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Move ${escape_html(selectedAssigned.length)} seat${escape_html(selectedAssigned.length === 1 ? "" : "s")} to another consultation session in this academic year.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <div class="space-y-3"><div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="reassign-target">Target session</label> `);
                      $$renderer6.select(
                        {
                          id: "reassign-target",
                          class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
                          value: reassignTargetId
                        },
                        ($$renderer7) => {
                          $$renderer7.option({ value: "" }, ($$renderer8) => {
                            $$renderer8.push(`Select a session…`);
                          });
                          $$renderer7.push(`<!--[-->`);
                          const each_array_2 = ensure_array_like(openReassignTargets());
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let target = each_array_2[$$index_2];
                            $$renderer7.option({ value: String(target.id) }, ($$renderer8) => {
                              $$renderer8.push(`${escape_html(target.code)} · ${escape_html(formatScheduledAt(target.scheduled_at))} · ${escape_html(target.room_name ?? target.room ?? "Room")} ·
                                ${escape_html(target.remaining ?? "—")} seats left`);
                            });
                          }
                          $$renderer7.push(`<!--]-->`);
                        }
                      );
                      $$renderer6.push(` `);
                      if (openReassignTargets().length === 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-muted-foreground mt-2 text-sm">No other sessions with remaining capacity.</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="reassign-reason">Reason (optional)</label> `);
                      Textarea($$renderer6, {
                        id: "reassign-reason",
                        rows: 3,
                        placeholder: "Why are they moving?",
                        get value() {
                          return reassignReason;
                        },
                        set value($$value) {
                          reassignReason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-10",
                              onclick: () => reassignOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              class: "min-h-10 gap-2",
                              disabled: reassigning || !reassignTargetId || selectedAssigned.length === 0,
                              onclick: submitReassign,
                              children: ($$renderer8) => {
                                Arrow_right_left($$renderer8, { class: "size-4" });
                                $$renderer8.push(`<!----> ${escape_html(reassigning ? "Reassigning…" : "Confirm reassign")}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!---->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
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
  ConsultationSessionShow as default
};
