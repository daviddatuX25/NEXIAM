import { h as head, e as escape_html, B as Button, x as store_get, f as attr_style, s as stringify, b as attr, a as ensure_array_like, y as unsubscribe_stores, d as derived } from "../app.js";
import { S as StaffLayout, C as Calendar, a as Send } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { f as formatDate, a as formatTimeRange } from "./date-utils-Bc5VPPy9.js";
import { b as bookingUnlocked } from "./booking-visibility-DG8T2RPN.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { B as Building_2 } from "./building-2-BaeEMGXC.js";
import { U as Users } from "./users-DWlguTa_.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
import { B as Bell } from "./PreferencesMenu-CCPeta-5.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right_left } from "./arrow-right-left-Cx5FZkqL.js";
import { S as Search, A as Activity } from "./settings-C5xOKpV3.js";
import { U as User_minus } from "./user-minus-CPyEYZhK.js";
import { U as User_plus } from "./user-plus-CCVf9WDs.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { B as Ban } from "./ban-CUQ_jw2o.js";
import { U as Undo_2 } from "./undo-2-CzBOZ6l_.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let {
      session,
      assigned_applicants = [],
      available_applicants = [],
      reassign_targets = [],
      publish_block_reason = null,
      is_publishable = false,
      is_physical_seating = false,
      snapshot_interpretation = null
    } = $$props;
    let selectedAvailable = [];
    let searchAvailable = "";
    let searchAssigned = "";
    let reasonOpen = false;
    let reasonMode = "unpublish";
    let reason = "";
    let selectedAssigned = [];
    let reassignOpen = false;
    let reassignTargetId = "";
    let reassignReason = "";
    let reassigning = false;
    let interpretationMode = "";
    let interpretationReason = "";
    let interpretationProvenance = "";
    let resolvingInterpretation = false;
    let interpretationErrors = {};
    const requiresSeatingInterpretation = derived(() => ["published", "in_progress", "completed"].includes(session.status) && snapshot_interpretation !== null && !["simple_capacity", "assigned_seating"].includes(snapshot_interpretation.seating_mode));
    const filteredAvailable = derived(() => available_applicants.filter((app) => {
      const term = searchAvailable.toLowerCase();
      return app.name && app.name.toLowerCase().includes(term) || app.reference_number && app.reference_number.toLowerCase().includes(term) || app.email && app.email.toLowerCase().includes(term);
    }));
    const filteredAssigned = derived(() => assigned_applicants.filter((app) => {
      const term = searchAssigned.toLowerCase();
      return app.name && app.name.toLowerCase().includes(term) || app.reference_number && app.reference_number.toLowerCase().includes(term);
    }));
    let availablePage = 1;
    const availablePageSize = 10;
    const totalAvailablePages = derived(() => Math.max(1, Math.ceil(filteredAvailable().length / availablePageSize)));
    const pagedAvailable = derived(() => filteredAvailable().slice((availablePage - 1) * availablePageSize, availablePage * availablePageSize));
    const isAllAvailableSelected = derived(() => pagedAvailable().length > 0 && pagedAvailable().every((app) => selectedAvailable.includes(app.id)));
    const isSomeAvailableSelected = derived(() => !isAllAvailableSelected() && pagedAvailable().some((app) => selectedAvailable.includes(app.id)));
    let assignedPage = 1;
    const assignedPageSize = 10;
    const totalAssignedPages = derived(() => Math.max(1, Math.ceil(filteredAssigned().length / assignedPageSize)));
    const pagedAssigned = derived(() => filteredAssigned().slice((assignedPage - 1) * assignedPageSize, assignedPage * assignedPageSize));
    const isAllAssignedSelected = derived(() => pagedAssigned().length > 0 && pagedAssigned().every((app) => selectedAssigned.includes(app.id)));
    const isSomeAssignedSelected = derived(() => !isAllAssignedSelected() && pagedAssigned().some((app) => selectedAssigned.includes(app.id)));
    function assignSelected() {
      if (selectedAvailable.length === 0) return;
      const ids = [...selectedAvailable];
      selectedAvailable = [];
      const postNext = (index) => {
        if (index >= ids.length) {
          router.reload();
          return;
        }
        router.post(`/exams/sessions/${session.id}/assign`, { admission_attempt_id: ids[index] }, { preserveScroll: true, onFinish: () => postNext(index + 1) });
      };
      postNext(0);
    }
    function removeAssigned(assignmentId) {
      router.post(`/exams/sessions/${session.id}/assignments/${assignmentId}/remove`, { reason: "Removed from session show" });
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
        `/exams/sessions/${session.id}/reassign`,
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
    const openReassignTargets = derived(() => reassign_targets.filter((t) => !t.full));
    function publish() {
      router.post(`/exams/sessions/${session.id}/publish`, { expected_version: session.version });
    }
    function openReservation() {
      router.post(`/exams/sessions/${session.id}/open-reservation`, { expected_version: session.version });
    }
    function closeReservation() {
      router.post(`/exams/sessions/${session.id}/close-reservation`, { expected_version: session.version });
    }
    function bookingModeLabel(mode) {
      if (mode === "open_for_reservation") return "Open for reservation";
      return "Staff only";
    }
    function openReason(mode) {
      reasonMode = mode;
      reason = "";
      reasonOpen = true;
    }
    function submitReason() {
      if (!reason.trim()) return;
      const path = reasonMode === "unpublish" ? `/exams/sessions/${session.id}/unpublish` : `/exams/sessions/${session.id}/cancel`;
      router.post(path, { expected_version: session.version, reason }, {
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
    const breadcrumbs = [
      { label: "Exam Scheduling", href: "/exams/sessions" },
      { label: session?.code ?? "Session" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("r80pzg", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(session?.code ?? "Exam session")}</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><div role="presentation">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap gap-2">`);
              if (session.status === "published" || session.status === "in_progress") {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: `/exams/sessions/${session.id}/monitoring`,
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      variant: "outline",
                      class: "min-h-10 gap-2",
                      children: ($$renderer7) => {
                        Activity($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Go to monitoring`);
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
                  href: `/exams/sessions/${session.id}/edit`,
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
                  class: "min-h-10 gap-2 text-destructive",
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
                  class: "min-h-10 gap-2 text-destructive",
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
          if (requiresSeatingInterpretation()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="space-y-4 rounded-xl border border-amber-500/40 bg-card p-6 shadow-xs" aria-labelledby="seating-interpretation-heading"><div class="space-y-1"><h2 id="seating-interpretation-heading" class="text-base font-semibold">Seating mode interpretation required</h2> <p class="text-sm text-muted-foreground">This historical snapshot does not establish whether applicants had assigned physical seats.
            Resolve its interpretation using retained publication or creation evidence, not today's institution settings or room layout.</p> <p class="text-xs text-muted-foreground">Snapshot #${escape_html(snapshot_interpretation.id)} · Revision ${escape_html(snapshot_interpretation.revision)}</p></div> <form class="space-y-4"><div class="space-y-2"><label for="interpretation-mode" class="text-sm font-medium">Seating mode</label> `);
            $$renderer4.select(
              {
                id: "interpretation-mode",
                value: interpretationMode,
                required: true,
                disabled: resolvingInterpretation,
                class: "h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "", disabled: true }, ($$renderer6) => {
                  $$renderer6.push(`Select the evidenced seating mode`);
                });
                $$renderer5.option({ value: "simple_capacity" }, ($$renderer6) => {
                  $$renderer6.push(`Simple capacity (no assigned physical seats)`);
                });
                $$renderer5.option({ value: "assigned_seating" }, ($$renderer6) => {
                  $$renderer6.push(`Assigned seating (physical seats assigned)`);
                });
              }
            );
            $$renderer4.push(` `);
            if (interpretationErrors.seating_mode) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(interpretationErrors.seating_mode)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2"><label for="interpretation-reason" class="text-sm font-medium">Resolution reason</label> `);
            Textarea($$renderer4, {
              id: "interpretation-reason",
              required: true,
              minlength: 5,
              disabled: resolvingInterpretation,
              placeholder: "Explain why the retained evidence establishes this seating mode.",
              get value() {
                return interpretationReason;
              },
              set value($$value) {
                interpretationReason = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (interpretationErrors.reason) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(interpretationErrors.reason)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div class="space-y-2"><label for="interpretation-provenance" class="text-sm font-medium">Provenance references</label> `);
            Textarea($$renderer4, {
              id: "interpretation-provenance",
              required: true,
              disabled: resolvingInterpretation,
              placeholder: "Identify the publication record, creation record, or migration evidence and its concrete reference.",
              get value() {
                return interpretationProvenance;
              },
              set value($$value) {
                interpretationProvenance = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (interpretationErrors.provenance_notes) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(interpretationErrors.provenance_notes)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (interpretationErrors.expected_version) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(interpretationErrors.expected_version)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (interpretationErrors.interpretation) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(interpretationErrors.interpretation)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            Button($$renderer4, {
              type: "submit",
              disabled: !interpretationMode,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html("Resolve seating interpretation")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></form></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="rounded-xl border border-border bg-card p-6 shadow-xs"><dl class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4"><div class="space-y-1 font-sans"><dt class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Date &amp; Time</dt> <dd class="space-y-0.5 pt-0.5"><div class="flex items-center gap-1.5 font-medium text-foreground text-sm">`);
          Calendar($$renderer4, { class: "size-4 text-primary shrink-0" });
          $$renderer4.push(`<!----> <span>${escape_html(formatDate(session.date, "long"))}</span></div> `);
          if (session.start_time) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">`);
            Clock($$renderer4, { class: "size-3.5 text-muted-foreground shrink-0" });
            $$renderer4.push(`<!----> <span>${escape_html(formatTimeRange(session.start_time, session.end_time))}</span></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></dd></div> <div class="space-y-1 font-sans"><dt class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Room &amp; Venue</dt> <dd class="space-y-0.5 pt-0.5"><div class="flex items-center gap-1.5 font-medium text-foreground text-sm">`);
          Building_2($$renderer4, { class: "size-4 text-primary shrink-0" });
          $$renderer4.push(`<!----> <span>${escape_html(session.room?.name ?? session.room?.code ?? "—")}</span></div> <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">`);
          Users($$renderer4, { class: "size-3.5 text-muted-foreground shrink-0" });
          $$renderer4.push(`<!----> <span>Capacity: ${escape_html(session.room?.capacity ?? "—")} Seats</span></div></dd></div> <div class="space-y-1 font-sans"><dt class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Academic Year</dt> <dd class="pt-0.5"><div class="flex items-center gap-1.5 font-medium text-foreground text-sm">`);
          Graduation_cap($$renderer4, { class: "size-4 text-primary shrink-0" });
          $$renderer4.push(`<!----> <span>${escape_html(session.academic_cycle?.label ?? "—")}</span></div> <div class="pt-1 flex items-center gap-1.5">`);
          Badge($$renderer4, {
            variant: statusVariant(session.status),
            class: "font-mono text-[10px] uppercase font-bold",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(session.status)}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          if (session.booking_mode === "open_for_reservation" || store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) {
            $$renderer4.push("<!--[0-->");
            Badge($$renderer4, {
              variant: session.booking_mode === "open_for_reservation" ? "default" : "outline",
              class: "text-[10px]",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(bookingModeLabel(session.booking_mode))}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></dd></div> <div class="space-y-1 font-mono"><dt class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Roster Allocation</dt> <dd class="space-y-1.5 pt-0.5"><div class="flex justify-between text-xs font-medium"><span class="text-foreground">${escape_html(assigned_applicants.length)} assigned</span> <span class="text-muted-foreground">${escape_html(session.room?.capacity ? Math.round(assigned_applicants.length / session.room.capacity * 100) : 0)}% filled</span></div> <div class="w-full bg-muted rounded-full h-2 overflow-hidden"><div class="bg-gradient-to-r from-primary to-emerald-400 h-2 rounded-full transition-all duration-300"${attr_style(`width: ${stringify(session.room?.capacity ? Math.min(100, Math.round(assigned_applicants.length / session.room.capacity * 100)) : 0)}%`)}></div></div> <div class="text-[10px] text-muted-foreground truncate">Proctors: ${escape_html((session.proctors ?? []).map((p) => p.name).join(", ") || "None assigned yet")}</div></dd></div></dl> `);
          if (session.effective_notification_policy) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="mt-4 pt-4 border-t border-border/60 flex flex-wrap items-center justify-between gap-3 text-xs"><div class="flex items-center gap-2 text-muted-foreground">`);
            Bell($$renderer4, { class: "size-3.5 text-primary shrink-0" });
            $$renderer4.push(`<!----> <span class="font-medium text-foreground">Communication:</span> <span>Publish email ${escape_html(session.effective_notification_policy.publication_email_enabled ? "enabled" : "disabled")} ·
              Reminders: ${escape_html((session.effective_notification_policy.reminder_offsets_minutes ?? []).map((m) => m === 4320 ? "72h" : m === 1440 ? "24h" : m === 120 ? "2h" : "1h").join(", ") || "None")}
              (${escape_html(session.effective_notification_policy.reminder_email_enabled ? "Email + In-App" : "In-App only")})</span></div> <div>`);
            if (session.notification_policy) {
              $$renderer4.push("<!--[0-->");
              Badge($$renderer4, {
                variant: "outline",
                class: "text-[10px] font-mono",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Custom session policy`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
              Badge($$renderer4, {
                variant: "secondary",
                class: "text-[10px] font-mono",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Academic year defaults`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer4.push(`<!--]--></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (session.material?.booklet || session.material?.seating_arrangement || session.material?.snapshot_id) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="mt-4 pt-4 border-t border-border/60 flex flex-wrap items-center justify-between gap-3 text-xs font-mono"><div class="flex flex-wrap items-center gap-4 text-muted-foreground"><div class="flex items-center gap-1.5">`);
            Book_open($$renderer4, { class: "size-3.5 text-muted-foreground shrink-0" });
            $$renderer4.push(`<!----> <span class="text-muted-foreground">Booklet:</span> <span class="font-bold text-foreground">${escape_html(session.material?.booklet?.label ?? "—")}</span></div> <div class="flex items-center gap-1.5"><span class="text-muted-foreground">Sheet:</span> <span class="font-medium text-foreground">${escape_html(session.material?.booklet?.answer_sheet ?? "—")}</span></div> `);
            if (is_physical_seating && session.material?.seating_arrangement) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex items-center gap-1.5"><span class="text-muted-foreground">Seating:</span> <span class="font-medium text-foreground">${escape_html(session.material.seating_arrangement.name)}</span></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (snapshot_interpretation) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex items-center gap-1.5"><span class="text-muted-foreground">Snapshot seating mode:</span> <span class="font-medium text-foreground">`);
              if (snapshot_interpretation.seating_mode === "simple_capacity") {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`Simple capacity (no assigned physical seats)`);
              } else if (snapshot_interpretation.seating_mode === "assigned_seating") {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`Assigned physical seats`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`Unknown (interpretation required)`);
              }
              $$renderer4.push(`<!--]--></span></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (session.material?.snapshot_id) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="px-2 py-0.5 rounded bg-muted text-[10px] font-semibold text-muted-foreground border border-border">Snapshot Frozen</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (session.material?.booklet) {
              $$renderer4.push("<!--[0-->");
              Link($$renderer4, {
                href: `/setup/exam-materials/booklets/${session.material.booklet.id}`,
                class: "text-[11px] text-primary hover:underline font-sans font-medium flex items-center gap-1",
                children: ($$renderer5) => {
                  $$renderer5.push(`<span>View Booklet</span> →`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="rounded-xl border border-border bg-card p-6 shadow-xs"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-semibold tracking-tight text-foreground">Assigned applicants (${escape_html(assigned_applicants.length)})</h2></div> `);
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
              class: "pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              type: "search",
              placeholder: "Search assigned by name or reference...",
              class: "h-9 pl-8 text-xs font-mono",
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
                                    if (session.status !== "completed" && session.status !== "cancelled" && pagedAssigned().length > 0) {
                                      $$renderer8.push("<!--[0-->");
                                      $$renderer8.push(`<input type="checkbox" class="size-4 rounded accent-primary cursor-pointer"${attr("checked", isAllAssignedSelected(), true)}${attr("indeterminate", isSomeAssignedSelected(), true)} aria-label="Select all assigned applicants on this page" title="Select all"/>`);
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
                              if (is_physical_seating) {
                                $$renderer7.push("<!--[0-->");
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Seat`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--> `);
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
                        const each_array = ensure_array_like(pagedAssigned());
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let row = each_array[$$index];
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              class: "hover:bg-muted/30 transition-colors",
                              children: ($$renderer7) => {
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (session.status !== "completed" && session.status !== "cancelled") {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<input type="checkbox" class="size-4 rounded accent-primary cursor-pointer"${attr("checked", selectedAssigned.includes(row.id), true)}${attr("aria-label", `Select ${row.name}`)}/>`);
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
                                    class: "font-mono text-xs font-semibold text-foreground",
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
                                    class: "font-medium text-foreground",
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
                                if (is_physical_seating) {
                                  $$renderer7.push("<!--[0-->");
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      class: "font-mono text-xs text-muted-foreground",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(row.seat_label ?? "—")}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]--> `);
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    class: "text-muted-foreground text-xs",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(row.source)}`);
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
                                      if (session.status !== "completed" && session.status !== "cancelled") {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<div class="flex flex-wrap items-center justify-center gap-1">`);
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
                                          class: "h-8 px-2 text-xs text-destructive hover:bg-destructive/10",
                                          onclick: () => removeAssigned(row.id),
                                          children: ($$renderer9) => {
                                            User_minus($$renderer9, { class: "mr-1.5 size-3.5" });
                                            $$renderer9.push(`<!----> Remove`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!----></div>`);
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
            $$renderer4.push(`</div> `);
            if (totalAssignedPages() > 1) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="mt-4 flex items-center justify-between border-t border-border/60 pt-3 text-xs font-mono"><span class="text-muted-foreground">Showing ${escape_html((assignedPage - 1) * assignedPageSize + 1)}–${escape_html(Math.min(assignedPage * assignedPageSize, filteredAssigned().length))} of ${escape_html(filteredAssigned().length)}</span> <div class="flex items-center gap-1.5">`);
              Button($$renderer4, {
                variant: "outline",
                size: "sm",
                class: "h-8 px-2.5 text-xs",
                disabled: assignedPage <= 1,
                onclick: () => assignedPage = Math.max(1, assignedPage - 1),
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Previous`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> <span class="px-2 text-muted-foreground">${escape_html(assignedPage)} / ${escape_html(totalAssignedPages())}</span> `);
              Button($$renderer4, {
                variant: "outline",
                size: "sm",
                class: "h-8 px-2.5 text-xs",
                disabled: assignedPage >= totalAssignedPages(),
                onclick: () => assignedPage = Math.min(totalAssignedPages(), assignedPage + 1),
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Next`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<p class="mt-4 text-sm text-muted-foreground">No applicants assigned yet.</p>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (session.status !== "completed" && session.status !== "cancelled") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-xl border border-border bg-card p-6 shadow-xs"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-base font-semibold tracking-tight text-foreground">Available applicants (${escape_html(filteredAvailable().length)})</h2></div> <div class="flex flex-wrap items-center gap-2">`);
            if (selectedAvailable.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-xs text-muted-foreground">Selected ${escape_html(selectedAvailable.length)} applicant(s)</span> `);
              Button($$renderer4, {
                variant: "ghost",
                size: "sm",
                class: "h-8 px-2 text-xs text-destructive hover:bg-destructive/10",
                onclick: () => selectedAvailable = [],
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
              disabled: selectedAvailable.length === 0,
              onclick: assignSelected,
              children: ($$renderer5) => {
                User_plus($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Assign selected (${escape_html(selectedAvailable.length)})`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></div> `);
            if (available_applicants.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="relative mt-4 max-w-sm">`);
              Search($$renderer4, {
                class: "pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                type: "search",
                placeholder: "Search available by name, reference, email...",
                class: "h-9 pl-8 text-xs font-mono",
                get value() {
                  return searchAvailable;
                },
                set value($$value) {
                  searchAvailable = $$value;
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
                                      if (pagedAvailable().length > 0) {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<input type="checkbox" class="size-4 rounded accent-primary cursor-pointer"${attr("checked", isAllAvailableSelected(), true)}${attr("indeterminate", isSomeAvailableSelected(), true)} aria-label="Select all available applicants on this page" title="Select all"/>`);
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
                          const each_array_1 = ensure_array_like(pagedAvailable());
                          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                            let app = each_array_1[$$index_1];
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                class: "hover:bg-muted/30 transition-colors",
                                children: ($$renderer7) => {
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<input type="checkbox" class="size-4 rounded accent-primary cursor-pointer"${attr("checked", selectedAvailable.includes(app.id), true)}${attr("aria-label", `Select ${app.name}`)}/>`);
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
                                      class: "font-mono text-xs font-semibold text-foreground",
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
                                      class: "font-medium text-foreground",
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
              $$renderer4.push(`</div> `);
              if (totalAvailablePages() > 1) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="mt-4 flex items-center justify-between border-t border-border/60 pt-3 text-xs font-mono"><span class="text-muted-foreground">Showing ${escape_html((availablePage - 1) * availablePageSize + 1)}–${escape_html(Math.min(availablePage * availablePageSize, filteredAvailable().length))} of ${escape_html(filteredAvailable().length)}</span> <div class="flex items-center gap-1.5">`);
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  class: "h-8 px-2.5 text-xs",
                  disabled: availablePage <= 1,
                  onclick: () => availablePage = Math.max(1, availablePage - 1),
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Previous`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> <span class="px-2 text-muted-foreground">${escape_html(availablePage)} / ${escape_html(totalAvailablePages())}</span> `);
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  class: "h-8 px-2.5 text-xs",
                  disabled: availablePage >= totalAvailablePages(),
                  onclick: () => availablePage = Math.min(totalAvailablePages(), availablePage + 1),
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Next`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="mt-4 text-sm text-muted-foreground">No eligible applicants available to assign.</p>`);
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
                                  $$renderer8.push(`<!---->${escape_html(reasonMode === "unpublish" ? "Unpublish session" : "Cancel session")}`);
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
                                  $$renderer8.push(`<!---->Move ${escape_html(selectedAssigned.length)} seat${escape_html(selectedAssigned.length === 1 ? "" : "s")} to another session in this academic year.
          Applicant notify on reassignment is not wired yet.`);
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
                      $$renderer6.push(` <div class="space-y-3"><div><label class="mb-1 block text-xs font-medium text-muted-foreground" for="reassign-target">Target session</label> `);
                      $$renderer6.select(
                        {
                          id: "reassign-target",
                          class: "flex min-h-10 w-full rounded-md border border-input bg-background px-3 text-sm",
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
                              $$renderer8.push(`${escape_html(target.code)} · ${escape_html(target.date)} ${escape_html(target.start_time)} · ${escape_html(target.room ?? "Room")} · ${escape_html(target.remaining)} seats left`);
                            });
                          }
                          $$renderer7.push(`<!--]-->`);
                        }
                      );
                      $$renderer6.push(` `);
                      if (openReassignTargets().length === 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="mt-2 text-sm text-muted-foreground">No other sessions with remaining capacity.</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <div><label class="mb-1 block text-xs font-medium text-muted-foreground" for="reassign-reason">Reason (optional)</label> `);
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
  Show as default
};
