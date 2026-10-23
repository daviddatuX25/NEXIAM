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
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { M as Map_pin } from "./map-pin-BSPrEyzp.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
import { C as Calendar_days } from "./calendar-days-CnJjYUSZ.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
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
function Consultation($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "denied",
      attempt = null,
      assignment = null,
      sessions = [],
      calendar_days = [],
      pipeline = [],
      message = ""
    } = $$props;
    let confirmOpen = false;
    let selectedSession = null;
    let reserving = false;
    let tourOpen = false;
    const headline = derived(() => {
      const map = {
        denied: "Consultation unavailable",
        not_result_ready: "Result not ready yet",
        no_open_sessions: "Consultation booking closed",
        booking_open: "Choose your consultation slot",
        assigned_or_reserved: "Your consultation appointment",
        advised_completed: "Consultation completed"
      };
      return map[page_state] ?? "Consultation schedule";
    });
    const consultationContext = derived(() => {
      return {
        entityKey: String(attempt?.id ?? "anonymous"),
        safetyKey: `${page_state}:${attempt?.advised ? "advised" : "unadvised"}`,
        freshness: "current",
        pageState: page_state,
        isAdvised: Boolean(attempt?.advised),
        hasAppointment: Boolean(assignment?.session),
        openSlotsCount: sessions.length
      };
    });
    function openConfirm(session) {
      if (session.full) return;
      selectedSession = session;
      confirmOpen = true;
    }
    function confirmReserve() {
      if (!selectedSession || !attempt) return;
      reserving = true;
      router.post(
        "/applicant/consultation/reserve",
        {
          consultation_session_id: selectedSession.id,
          admission_attempt_id: attempt.id,
          idempotency_key: `consultation-reserve-${attempt.id}-${selectedSession.id}`
        },
        {
          preserveScroll: true,
          onFinish: () => {
            reserving = false;
            confirmOpen = false;
            selectedSession = null;
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
    function dateLabel(iso) {
      if (!iso) return "Date TBD";
      return new Date(iso).toLocaleString(void 0, {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit"
      });
    }
    const slotGroups = derived(() => {
      if (calendar_days && calendar_days.length > 0) {
        return calendar_days.map((day) => ({
          date: day.date,
          label: String(dayNumber(day.date)),
          caption: `${weekday(day.date)} · ${monthShort(day.date)} · ${day.date}`,
          slots: (day.slots ?? []).map((slot) => ({
            id: slot.id,
            timeLabel: slot.time_label ?? dateLabel(slot.scheduled_at),
            venue: slot.room ?? null,
            code: slot.code,
            remaining: slot.remaining,
            capacity: slot.capacity,
            full: slot.full
          }))
        }));
      }
      return [
        {
          date: null,
          slots: sessions.map((session) => ({
            id: session.id,
            timeLabel: dateLabel(session.scheduled_at),
            venue: session.room ?? null,
            code: session.code,
            remaining: session.remaining,
            capacity: session.capacity,
            full: session.full
          }))
        }
      ];
    });
    const confirmRows = derived(() => selectedSession ? [
      { label: "Session", value: selectedSession.code },
      {
        label: "Seats left",
        value: String(selectedSession.remaining)
      }
    ] : []);
    const confirmDescription = derived(() => selectedSession ? `Reserve ${dateLabel(selectedSession.scheduled_at)} at ${selectedSession.room ?? "the assigned room"}?` : "Confirm your selected consultation session.");
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("15xpui7", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Consultation schedule</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "Consultation",
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-lg space-y-5"><div class="space-y-1" data-tour="consultation-header"><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight">${escape_html(headline())}</h1> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "applicant-consultation",
            label: "Need guidance on your consultation? Ask Kuya Xiam!",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----></div> `);
          if (attempt) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="font-mono text-sm text-muted-foreground">Ref ${escape_html(attempt.reference_code)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (attempt?.advised) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="mt-2">`);
            Badge($$renderer4, {
              variant: "default",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Advised`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="sr-only">`);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----></div> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              class: "overflow-hidden shadow-sm",
              "data-tour": "consultation-progress",
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
                            $$renderer7.push(`<!---->Result → schedule → appointment → advising`);
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
                      if (pipeline && pipeline.length > 0) {
                        $$renderer6.push("<!--[0-->");
                        SchedulePipeline($$renderer6, { stages: pipeline });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(message || "Consultation scheduling progress.")}</p>`);
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
          $$renderer4.push(` <div data-tour="consultation-content">`);
          if (page_state === "booking_open") {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden border-primary/25 shadow-sm",
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
                              $$renderer7.push(`<!---->Available sessions`);
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
                              $$renderer7.push(`<!---->Pick a session to reserve your consultation seat. Seats left update as others book.`);
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
                        if (slotGroups()[0]?.slots.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">No open sessions yet. Check back soon.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          ReservationSlotList($$renderer6, { groups: slotGroups(), onSelect: openConfirm });
                        }
                        $$renderer6.push(`<!--]--> <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">`);
                        Shield_alert($$renderer6, { class: "mt-0.5 size-3.5 shrink-0" });
                        $$renderer6.push(`<!----> Confirming books your consultation immediately. The office may still reschedule you if needed.</p>`);
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
          } else if (page_state === "assigned_or_reserved" && assignment?.session) {
            $$renderer4.push("<!--[1-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden border-primary/25 shadow-sm",
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
                              $$renderer7.push(`<!---->Consultation schedule`);
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
                              $$renderer7.push(`<!---->${escape_html(assignment.session.published ? "Official schedule published" : "Scheduled — awaiting official publish")}`);
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
                        $$renderer6.push(`<!----> <div><p class="font-medium">${escape_html(dateLabel(assignment.session.scheduled_at))}</p> <p class="text-sm text-muted-foreground">${escape_html(assignment.session.code)}</p></div></div> <div class="flex items-start gap-3">`);
                        Map_pin($$renderer6, { class: "mt-0.5 size-5 text-primary" });
                        $$renderer6.push(`<!----> <p class="font-medium">${escape_html(assignment.session.room ?? "Room TBD")}</p></div> `);
                        Badge($$renderer6, {
                          variant: assignment.session.published ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(assignment.session.published ? "Official schedule" : "Awaiting publish")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        if (assignment.source === "applicant_reserved") {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground">You reserved this consultation slot yourself.</p>`);
                        } else if (assignment.source === "staff_assigned") {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground">Guidance counselors assigned this consultation session.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> <div class="rounded-xl border border-border/70 bg-muted/20 p-3.5 text-xs text-muted-foreground space-y-1.5"><p class="font-medium text-foreground">What to prepare:</p> <ul class="list-disc space-y-1 pl-4"><li>Bring your physical Form 138 / TOR report card for verification.</li> <li>Bring a valid school or government photo ID.</li> <li>Arrive 15 minutes before your scheduled window.</li></ul></div>`);
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
          } else if (page_state === "advised_completed") {
            $$renderer4.push("<!--[2-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-emerald-500/40 bg-emerald-500/5 shadow-sm",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 p-6 text-center",
                      children: ($$renderer6) => {
                        Circle_check($$renderer6, {
                          class: "mx-auto size-10 text-emerald-600 dark:text-emerald-400"
                        });
                        $$renderer6.push(`<!----> <div class="space-y-1"><h2 class="text-lg font-semibold text-foreground">Consultation completed</h2> <p class="text-sm text-muted-foreground">Your one-on-one session with the Guidance Counselor has been completed, and degree program recommendations have been recorded.</p></div> `);
                        Link($$renderer6, {
                          href: "/applicant/program-choice",
                          class: "inline-block pt-1",
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              class: "min-h-11",
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Review program choice`);
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
          } else if (page_state === "not_result_ready") {
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
                        $$renderer6.push(`<!----> <p class="font-medium">Result not ready yet</p> <p class="text-sm text-muted-foreground">${escape_html(message || "Consultation unlocks after entrance exam results are finalized. Check back once scoring and result preparation are complete.")}</p>`);
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
          } else if (page_state === "no_open_sessions") {
            $$renderer4.push("<!--[4-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden shadow-sm",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-muted/20 pb-3",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center gap-2.5">`);
                        Calendar_days($$renderer6, { class: "size-5 text-muted-foreground" });
                        $$renderer6.push(`<!----> `);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-base",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Consultation booking window`);
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
                      class: "space-y-4 p-6",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="space-y-2 text-sm leading-relaxed text-muted-foreground"><p class="font-medium text-foreground">No open reservation window currently active.</p> <p>Guidance counselors schedule consultation sessions in waves. You may be assigned directly to an upcoming counseling slot by Guidance staff, or open reservation dates will be posted here soon.</p></div> <div class="rounded-xl border border-border/70 bg-muted/30 p-4 text-xs text-muted-foreground space-y-2"><p class="font-semibold text-foreground flex items-center gap-1.5">`);
                        File_text($$renderer6, { class: "size-4 text-primary" });
                        $$renderer6.push(`<!----> Required documents to prepare:</p> <ul class="list-disc space-y-1 pl-4"><li>Hard-copy Senior High Form 138 / TOR report card.</li> <li>Valid photo ID (School ID or Government-issued ID).</li> <li>Printed or digital copy of your NEXIAM QR pass.</li></ul></div>`);
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
            $$renderer4.push("<!--[5-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "p-6 text-center text-sm text-muted-foreground",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(message || "This account cannot access consultation scheduling.")}`);
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
          $$renderer4.push(`<!--]--></div></div> `);
          ReservationConfirmSheet($$renderer4, {
            open: confirmOpen,
            title: "Confirm consultation",
            description: confirmDescription(),
            rows: confirmRows(),
            confirmLabel: "Confirm consultation",
            busyLabel: "Booking…",
            busy: reserving,
            onConfirm: confirmReserve,
            onClose: () => {
              confirmOpen = false;
              selectedSession = null;
            }
          });
          $$renderer4.push(`<!----> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-consultation",
            context: consultationContext(),
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
  Consultation as default
};
