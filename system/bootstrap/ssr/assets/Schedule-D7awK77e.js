import { h as head, e as escape_html, B as Button, d as derived } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import SchedulePipeline from "./SchedulePipeline-B-MciuGr.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { R as ReservationSlotList, a as ReservationConfirmSheet } from "./ReservationConfirmSheet-BlAWtMT6.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { M as Map_pin } from "./map-pin-BSPrEyzp.js";
import { C as Calendar_days } from "./calendar-days-CnJjYUSZ.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./dialog-description-D12L0QA9.js";
import "./dialog-footer-kNnwKT2h.js";
import "qrcode";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./chevron-right-BHu-PGOg.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
import "./circle-DadbbVvn.js";
function Schedule($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "denied",
      attempt = null,
      assignment = null,
      application = null,
      calendar_days = [],
      message = "",
      pipeline = [],
      has_assigned_seat_instruction = false
    } = $$props;
    let confirmOpen = false;
    let selectedSlot = null;
    let reserving = false;
    let tourOpen = false;
    const allSlots = derived(() => (calendar_days ?? []).flatMap((day) => day.slots ?? []));
    const availableSlots = derived(() => allSlots().filter((slot) => !slot.full));
    const scheduleContext = derived(() => {
      const entityKey = String(attempt?.id ?? "anonymous");
      const hasAnySelectable = availableSlots().length > 0;
      const isPub = Boolean(assignment?.session?.published);
      const safetyKey = `${page_state}:${application?.needs_correction ? "correction" : "ok"}:${isPub ? "pub" : "unpub"}:${hasAnySelectable ? "avail" : "full"}:${has_assigned_seat_instruction === true ? "assigned-seat-instruction" : "no-seat-instruction"}`;
      return {
        entityKey,
        safetyKey,
        freshness: "current",
        pageState: page_state,
        hasAssignedSeatInstruction: has_assigned_seat_instruction === true,
        message: message || "",
        needsCorrection: Boolean(application?.needs_correction),
        canOpenCorrection: Boolean(application?.needs_correction && application?.actions?.some((a) => a.key === "fix_application")),
        slotCount: allSlots().length,
        availableSlotCount: availableSlots().length,
        reservationError: null,
        appointment: assignment?.session ? {
          date: assignment.session.date ?? null,
          startTime: assignment.session.start_time ?? null,
          endTime: assignment.session.end_time ?? null,
          room: assignment.session.room ?? null,
          published: isPub,
          source: assignment.source ?? "unknown"
        } : null,
        schedulingPolicy: null
      };
    });
    const hasPresentAttendance = derived(() => assignment?.attendance_outcome === "present");
    const headline = derived(() => {
      if (hasPresentAttendance()) {
        return "Exam Completed";
      }
      const map = {
        denied: "Schedule unavailable",
        pending_acceptance: "Waiting for acceptance",
        waiting_for_reservation_open: "Scheduling not open yet",
        calendar_open: "Choose your exam slot",
        assigned_published: assignment?.source === "applicant_reserved" ? "Reserved by you" : assignment?.source === "staff_assigned" ? "Assigned by Guidance" : "Your exam appointment"
      };
      return map[page_state] ?? "Exam schedule";
    });
    function openConfirm(slot) {
      if (slot.full) return;
      selectedSlot = slot;
      confirmOpen = true;
    }
    function confirmReserve() {
      if (!selectedSlot || !attempt) return;
      reserving = true;
      router.post(
        "/applicant/schedule/reserve",
        {
          exam_session_id: selectedSlot.id,
          admission_attempt_id: attempt.id,
          idempotency_key: `reserve-${attempt.id}-${selectedSlot.id}`
        },
        {
          preserveScroll: true,
          onFinish: () => {
            reserving = false;
            confirmOpen = false;
            selectedSlot = null;
          }
        }
      );
    }
    function weekday(dateStr) {
      return (/* @__PURE__ */ new Date(`${dateStr}T12:00:00`)).toLocaleDateString(void 0, { weekday: "short" });
    }
    function dayNumber(dateStr) {
      return (/* @__PURE__ */ new Date(`${dateStr}T12:00:00`)).getDate();
    }
    function monthShort(dateStr) {
      return (/* @__PURE__ */ new Date(`${dateStr}T12:00:00`)).toLocaleDateString(void 0, { month: "short" });
    }
    const slotGroups = derived(() => calendar_days.map((day) => ({
      date: day.date,
      label: String(dayNumber(day.date)),
      caption: `${weekday(day.date)} · ${monthShort(day.date)} · ${day.date}`,
      slots: day.slots.map((slot) => ({
        id: slot.id,
        timeLabel: `${slot.start_time}${slot.end_time ? `–${slot.end_time}` : ""}`,
        venue: slot.room ?? null,
        code: slot.code,
        remaining: slot.remaining,
        capacity: slot.capacity,
        full: slot.full
      }))
    })));
    const confirmRows = derived(() => selectedSlot ? [
      { label: "Session", value: selectedSlot.code },
      { label: "Slots left", value: String(selectedSlot.remaining) }
    ] : []);
    const confirmDescription = derived(() => selectedSlot ? `Book ${selectedSlot.start_time}${selectedSlot.end_time ? `–${selectedSlot.end_time}` : ""} at ${selectedSlot.room ?? "the assigned room"}?` : "Confirm your selected exam slot.");
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1dixrmf", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Your status</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "Your status",
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-lg space-y-5"><div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight">${escape_html(headline())}</h1> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "applicant-exam-schedule",
            label: "Need help with your exam schedule? Ask Kuya Xiam!",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----></div> `);
          if (attempt) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="font-mono text-sm text-muted-foreground">Ref ${escape_html(attempt.reference_code)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="sr-only">`);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----></div> `);
          if (application?.needs_correction) {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-amber-500/40 bg-amber-500/10",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<p class="font-medium">Registrar needs a correction</p> `);
                        if (application.correction_notes) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(application.correction_notes)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (application.correction_href) {
                          $$renderer6.push("<!--[0-->");
                          Link($$renderer6, {
                            href: application.correction_href,
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                class: "min-h-11 w-full",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Fix application`);
                                },
                                $$slots: { default: true }
                              });
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
          } else if (application?.can_quiet_amend && application.correction_href) {
            $$renderer4.push("<!--[1-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">Still pending — you can quietly update your application.</p> `);
                        Link($$renderer6, {
                          href: application.correction_href,
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              class: "min-h-11",
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Update application`);
                              },
                              $$slots: { default: true }
                            });
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
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
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              class: "overflow-hidden shadow-sm",
              "data-tour": "exam-schedule-progress",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    class: "border-b bg-muted/20 pb-3",
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-lg",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Your progress`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Admission → schedule → confirmed appointment`);
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
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "pt-5",
                    children: ($$renderer6) => {
                      if (pipeline.length > 0) {
                        $$renderer6.push("<!--[0-->");
                        SchedulePipeline($$renderer6, { stages: pipeline });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(message || "No schedule progress yet.")}</p>`);
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
          $$renderer4.push(` `);
          if (page_state === "calendar_open") {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden border-primary/25 shadow-sm",
                "data-tour": "exam-schedule-slots",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-primary/5 pb-3",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div>`);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Available slots`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Card_description) {
                          $$renderer6.push("<!--[-->");
                          Card_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Tap a time to reserve your slot. Available slots update as others book.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div>`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 pt-4",
                      children: ($$renderer6) => {
                        if (slotGroups().length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">No open sessions yet. Check back soon.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          ReservationSlotList($$renderer6, { groups: slotGroups(), onSelect: openConfirm });
                        }
                        $$renderer6.push(`<!--]--> <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">`);
                        Shield_alert($$renderer6, { class: "mt-0.5 size-3.5 shrink-0" });
                        $$renderer6.push(`<!----> Confirming reserves your slot immediately. Campus emergencies may still require rescheduling.</p>`);
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
          } else if (page_state === "assigned_published" && assignment?.session) {
            $$renderer4.push("<!--[1-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden border-primary/25 shadow-sm",
                "data-tour": "exam-schedule-appointment",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-primary/5 pb-3",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              if (hasPresentAttendance()) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`Exam Completed`);
                              } else if (assignment.source === "applicant_reserved") {
                                $$renderer7.push("<!--[1-->");
                                $$renderer7.push(`Reserved by you`);
                              } else if (assignment.source === "staff_assigned") {
                                $$renderer7.push("<!--[2-->");
                                $$renderer7.push(`Assigned by Guidance`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                                $$renderer7.push(`Exam appointment`);
                              }
                              $$renderer7.push(`<!--]-->`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Card_description) {
                          $$renderer6.push("<!--[-->");
                          Card_description($$renderer6, {
                            children: ($$renderer7) => {
                              if (hasPresentAttendance()) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`Attendance recorded — examination completed`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                                $$renderer7.push(`${escape_html(assignment.session.published ? "Schedule published" : "Assigned — awaiting official publish")}`);
                              }
                              $$renderer7.push(`<!--]-->`);
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
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 pt-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-start gap-3">`);
                        Circle_check($$renderer6, { class: "mt-0.5 size-5 text-primary" });
                        $$renderer6.push(`<!----> <div><p class="font-medium">${escape_html(assignment.session.date)} · ${escape_html(assignment.session.start_time)}`);
                        if (assignment.session.end_time) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`–${escape_html(assignment.session.end_time)}`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></p> <p class="text-sm text-muted-foreground">${escape_html(assignment.session.code)}</p></div></div> <div class="flex items-start gap-3">`);
                        Map_pin($$renderer6, { class: "mt-0.5 size-5 text-primary" });
                        $$renderer6.push(`<!----> <p class="font-medium">${escape_html(assignment.session.room ?? "Room TBD")}</p></div> <div class="flex flex-wrap items-center gap-2">`);
                        if (hasPresentAttendance()) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: "outline",
                            class: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Exam Completed`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Badge($$renderer6, {
                            variant: assignment.session.published ? "default" : "secondary",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(assignment.session.published ? "Official schedule" : "Awaiting publish")}`);
                            },
                            $$slots: { default: true }
                          });
                        }
                        $$renderer6.push(`<!--]--> `);
                        Badge($$renderer6, {
                          variant: "outline",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(assignment.source === "applicant_reserved" ? "Reserved by you" : "Assigned by Guidance")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div> `);
                        if (has_assigned_seat_instruction === true && !hasPresentAttendance()) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">Follow staff-provided assigned-seat instructions at the venue.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (assignment.source === "applicant_reserved") {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground">Reserved by you directly through the portal.</p>`);
                        } else if (assignment.source === "staff_assigned") {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground">Assigned by Guidance staff.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
          } else if (page_state === "waiting_for_reservation_open") {
            $$renderer4.push("<!--[2-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-6 text-center",
                      children: ($$renderer6) => {
                        Calendar_days($$renderer6, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">Reservation window closed or not opened</p> <p class="text-sm text-muted-foreground">${escape_html(message || "When Guidance opens a session for booking, you can pick a time here. Staff may also assign you directly.")}</p>`);
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
          } else if (page_state === "pending_acceptance") {
            $$renderer4.push("<!--[3-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-6 text-center",
                      children: ($$renderer6) => {
                        Clock_3($$renderer6, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">Acceptance comes first</p> <p class="text-sm text-muted-foreground">${escape_html(message || "Exam scheduling unlocks after the registrar accepts your application.")}</p>`);
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
          } else if (page_state === "denied") {
            $$renderer4.push("<!--[4-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "p-6 text-center text-sm text-muted-foreground",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(message || "This account cannot access exam scheduling.")}`);
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
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          ReservationConfirmSheet($$renderer4, {
            open: confirmOpen,
            title: "Confirm exam slot",
            description: confirmDescription(),
            rows: confirmRows(),
            confirmLabel: "Confirm reservation",
            busyLabel: "Booking…",
            busy: reserving,
            onConfirm: confirmReserve,
            onClose: () => {
              confirmOpen = false;
              selectedSlot = null;
            }
          });
          $$renderer4.push(`<!----> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-exam-schedule",
            context: scheduleContext(),
            get open() {
              return tourOpen;
            },
            set open($$value) {
              tourOpen = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!---->`);
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
  Schedule as default
};
