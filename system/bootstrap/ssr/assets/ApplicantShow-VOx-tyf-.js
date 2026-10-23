import { h as head, e as escape_html, a as ensure_array_like, B as Button, b as attr, s as stringify, d as derived, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Table_caption } from "./table-caption-Cs_SOvQm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
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
import "./input-BUZAZKIK.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
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
import "./check-DK45Y9SJ.js";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function ApplicantShow($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "denied", record = null } = $$props;
    const programCode = derived(() => record?.selection?.selection_program ?? "");
    const breadcrumbs = derived(() => [
      { label: "My programs", href: "/staff/programs" },
      { label: record?.reference_code ?? "Applicant" }
    ]);
    const declineForm = useForm({ program_code: "", reason: "", idempotency_key: "" });
    let declineDialogOpen = false;
    const canDecline = derived(() => record?.tier === "B" && !!record?.selection?.selection_program && !(record?.stamp_state?.enrolling ?? false) && !(record?.stamp_state?.enrolled ?? false));
    function openDecline() {
      declineForm.clearErrors();
      declineForm.program_code = record?.selection?.selection_program ?? "";
      declineForm.reason = "";
      declineForm.idempotency_key = newIdempotencyKey();
      declineDialogOpen = true;
    }
    function submitDecline() {
      if (!record) return;
      const program = record.selection?.selection_program ?? "";
      const url = typeof route === "function" ? route("program-head.applicants.decline", { program, attempt: record.attempt_id }) : `/staff/programs/${program}/applicants/${record.attempt_id}/decline`;
      declineForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
          declineDialogOpen = false;
        }
      });
    }
    const resolveForm = useForm({ outcome: "", reason: "", idempotency_key: "" });
    let resolveDialogOpen = false;
    let activeFollowUp = null;
    const resolveOutcomes = [
      { value: "cleared_by_ph", label: "Cleared by program head" },
      { value: "declined_by_ph", label: "Declined by program head" },
      { value: "appearance_requested", label: "Appearance requested" }
    ];
    function openResolve(row) {
      activeFollowUp = row;
      resolveForm.clearErrors();
      resolveForm.outcome = "cleared_by_ph";
      resolveForm.reason = "";
      resolveForm.idempotency_key = newIdempotencyKey();
      resolveDialogOpen = true;
    }
    function submitResolve() {
      if (!activeFollowUp) return;
      const url = typeof route === "function" ? route("program-head.follow-ups.resolve", { program: programCode(), followUp: activeFollowUp.id }) : `/staff/programs/${programCode()}/follow-ups/${activeFollowUp.id}/resolve`;
      resolveForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
          resolveDialogOpen = false;
          activeFollowUp = null;
        }
      });
    }
    const resolveReasonRequired = derived(() => resolveForm.outcome !== "appearance_requested" && !resolveForm.reason.trim());
    const lifecycleLabels = {
      accepted: "Accepted",
      result_released: "Result released",
      advised: "Advised",
      selection_pending_ack: "Selection pending ack",
      selected: "Selected",
      enrolling: "Enrolling",
      enrolled: "Enrolled"
    };
    function lifecycleLabel(value) {
      return lifecycleLabels[value] ?? value ?? "—";
    }
    function lifecycleVariant(value) {
      if (value === "enrolled" || value === "enrolling" || value === "selected") return "default";
      if (value === "selection_pending_ack") return "outline";
      return "secondary";
    }
    const followUpStatusLabels = {
      open: "Open",
      appearance_requested: "Appearance requested",
      cleared_by_ph: "Cleared by program head",
      declined_by_ph: "Declined by program head"
    };
    function followUpStatusLabel(status) {
      return followUpStatusLabels[status] ?? status ?? "—";
    }
    function followUpStatusVariant(status) {
      if (status === "open") return "default";
      if (status === "appearance_requested") return "outline";
      if (status === "declined_by_ph") return "destructive";
      return "secondary";
    }
    function formatDateTime(iso) {
      if (!iso) return "—";
      const d = new Date(iso);
      if (isNaN(d.getTime())) return iso;
      return d.toLocaleString(void 0, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit"
      });
    }
    function formatScore(value) {
      if (value === null || value === void 0 || value === "") return "—";
      const numeric = Number(value);
      return Number.isNaN(numeric) ? String(value) : (
        // converted-score text (e.g. "Above Average")
        numeric.toLocaleString(void 0, { maximumFractionDigits: 1 })
      );
    }
    const scoreProfile = derived(() => record?.score_profile ?? null);
    const series = derived(() => scoreProfile()?.series ?? []);
    const chart = derived(() => {
      const max = Math.max(1, ...series().map((s) => Number(s.score) || 0));
      const barHeight = 18;
      const rowGap = 16;
      const labelWidth = 170;
      const valueWidth = 64;
      const chartWidth = 760;
      const plotWidth = chartWidth - labelWidth - valueWidth;
      const chartHeight = series().length > 0 ? series().length * (barHeight + rowGap) + 8 : 0;
      return {
        max,
        barHeight,
        rowGap,
        labelWidth,
        valueWidth,
        chartWidth,
        plotWidth,
        chartHeight
      };
    });
    const consultationOutcome = derived(() => record?.consultation_outcome ?? null);
    const selection = derived(() => record?.selection ?? null);
    const followUps = derived(() => record?.follow_ups ?? []);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1oj1fqn", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Applicant record</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: breadcrumbs(),
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          if (page_state !== "ready") {
            $$renderer4.push("<!--[0-->");
            E2PageState($$renderer4, { state: page_state });
          } else if (record) {
            $$renderer4.push("<!--[1-->");
            if (declineForm.errors?.decline) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">${escape_html(declineForm.errors.decline)}</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-muted/20 pb-3",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div>`);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(record.name)}`);
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
                            class: "font-mono text-xs",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(record.reference_code)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div> <div class="flex flex-wrap items-center gap-2">`);
                        if (record.lifecycle) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: lifecycleVariant(record.lifecycle),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(lifecycleLabel(record.lifecycle))}`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        Badge($$renderer6, {
                          variant: record.tier === "B" ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Tier ${escape_html(record.tier)}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        if (record.tier === "B") {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: "destructive",
                            children: ($$renderer7) => {
                              Shield_alert($$renderer7, { class: "mr-1 size-3" });
                              $$renderer7.push(`<!----> Protected record`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div></div>`);
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
                      class: "space-y-3 pt-5 text-sm",
                      children: ($$renderer6) => {
                        if (record.tier === "A" && (record.basis ?? []).length > 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Basis</span> <span class="flex flex-wrap justify-end gap-1"><!--[-->`);
                          const each_array = ensure_array_like(record.basis);
                          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                            let basis = each_array[$$index];
                            Badge($$renderer6, {
                              variant: "outline",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(basis)}`);
                              },
                              $$slots: { default: true }
                            });
                          }
                          $$renderer6.push(`<!--]--></span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.advised !== void 0 && record.advised !== null) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Advised</span> <span class="font-medium">${escape_html(record.advised ? "Yes" : "No")}</span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.selection_status !== void 0 && record.selection_status !== null) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Selection status</span> <span class="font-mono text-xs font-semibold uppercase tracking-wide">${escape_html(record.selection_status)}</span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.follow_up_count !== void 0 && record.follow_up_count !== null) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Open follow-ups</span> <span class="tabular-nums font-semibold">${escape_html(record.follow_up_count)}</span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.stamp_state) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Enrollment</span> <span class="font-medium">${escape_html(record.stamp_state.enrolled ? "Enrolled" : record.stamp_state.enrolling ? "Enrolling" : "Not enrolling")}</span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.visibility_note) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="border-border rounded-md border bg-muted/30 p-3 text-sm"><p class="flex items-center gap-2 font-medium">`);
                          Lock($$renderer6, { class: "size-4 text-muted-foreground" });
                          $$renderer6.push(`<!----> ${escape_html(record.visibility_note)}</p></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (record.tier === "B" && record.access_reason) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm"><p class="flex items-center gap-2 font-medium">`);
                          Shield_alert($$renderer6, { class: "size-4" });
                          $$renderer6.push(`<!----> Access reason</p> <p class="mt-1">${escape_html(record.access_reason)}</p></div>`);
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
            $$renderer4.push(` `);
            if (record.tier === "B" && selection()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Selection`);
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
                                $$renderer7.push(`<!---->${escape_html(selection().version ? `Pool version ${selection().version}` : "No active pool")}`);
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
                        class: "space-y-3 pt-4 text-sm",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Selected program</span> <span class="font-mono text-xs font-semibold text-foreground">${escape_html(selection().selection_program ?? "—")}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Status</span> <span class="font-mono text-xs font-semibold uppercase tracking-wide">${escape_html(selection().selection_status ?? "—")}</span></div> `);
                          if ((selection().options ?? []).length > 0) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div><p class="mb-1 text-muted-foreground">Options</p> <ul class="divide-y rounded-md border"><!--[-->`);
                            const each_array_1 = ensure_array_like(selection().options);
                            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                              let option = each_array_1[$$index_1];
                              $$renderer6.push(`<li class="flex items-center justify-between gap-2 px-3 py-2"><span class="font-medium">${escape_html(option.program_name)}</span> <span class="flex items-center gap-2">`);
                              if (option.basis) {
                                $$renderer6.push("<!--[0-->");
                                Badge($$renderer6, {
                                  variant: "outline",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->${escape_html(option.basis)}`);
                                  },
                                  $$slots: { default: true }
                                });
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--> <span class="font-mono text-xs text-muted-foreground">${escape_html(option.program_code)}</span></span></li>`);
                            }
                            $$renderer6.push(`<!--]--></ul></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (canDecline()) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="flex justify-end pt-1">`);
                            Button($$renderer6, {
                              type: "button",
                              variant: "destructive",
                              class: "min-h-10",
                              onclick: openDecline,
                              children: ($$renderer7) => {
                                Triangle_alert($$renderer7, { class: "size-4" });
                                $$renderer7.push(`<!----> Decline selection`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></div>`);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (record.tier === "B" && scoreProfile()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div>`);
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(scoreProfile().title ?? "Released score profile")}`);
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
                                $$renderer7.push(`<!---->Unit: ${escape_html(scoreProfile().unit ?? "score")} `);
                                if (scoreProfile().source) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`· ${escape_html(scoreProfile().source)}`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]--> `);
                                if (scoreProfile().version) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`· v${escape_html(scoreProfile().version)}`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]--> `);
                                if (scoreProfile().released_at) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`· released ${escape_html(formatDateTime(scoreProfile().released_at))}`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]--> `);
                                if (scoreProfile().freshness) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`· ${escape_html(scoreProfile().freshness)}`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
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
                          $$renderer6.push(`</div></div>`);
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
                          if (series().length === 0) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-muted-foreground py-8 text-center">No score values are available for this record.</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<svg role="img" aria-labelledby="score-chart-title score-chart-desc"${attr("viewBox", `0 0 ${stringify(chart().chartWidth)} ${stringify(chart().chartHeight)}`)} class="h-auto w-full"><title id="score-chart-title">${escape_html(scoreProfile().title ?? "Released score profile")}</title><desc id="score-chart-desc">Bar chart of released scores by aptitude area. The identical values are listed in the table below.</desc><!--[-->`);
                            const each_array_2 = ensure_array_like(series());
                            for (let i = 0, $$length = each_array_2.length; i < $$length; i++) {
                              let s = each_array_2[i];
                              const y = i * (chart().barHeight + chart().rowGap) + 4;
                              const barWidth = series().length > 0 ? (Number(s.score) || 0) / chart().max * chart().plotWidth : 0;
                              $$renderer6.push(`<text${attr("x", chart().labelWidth - 10)}${attr("y", y + chart().barHeight / 2 + 4)} text-anchor="end" class="fill-muted-foreground text-[11px]">${escape_html(s.area_name)}</text><rect${attr("x", chart().labelWidth)}${attr("y", y)}${attr("width", barWidth)}${attr("height", chart().barHeight)} rx="3" class="fill-primary/80"></rect><text${attr("x", chart().labelWidth + chart().plotWidth + 10)}${attr("y", y + chart().barHeight / 2 + 4)} class="fill-foreground text-[11px] tabular-nums">${escape_html(formatScore(s.score))}</text>`);
                            }
                            $$renderer6.push(`<!--]--></svg>`);
                            if (Table) {
                              $$renderer6.push("<!--[-->");
                              Table($$renderer6, {
                                class: "w-full text-sm",
                                children: ($$renderer7) => {
                                  if (Table_caption) {
                                    $$renderer7.push("<!--[-->");
                                    Table_caption($$renderer7, {
                                      class: "text-left text-xs text-muted-foreground",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Score profile — equivalent values to the chart above.`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_header) {
                                    $$renderer7.push("<!--[-->");
                                    Table_header($$renderer7, {
                                      children: ($$renderer8) => {
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_head) {
                                                $$renderer9.push("<!--[-->");
                                                Table_head($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->Area`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              if (Table_head) {
                                                $$renderer9.push("<!--[-->");
                                                Table_head($$renderer9, {
                                                  class: "text-right",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->Score`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              if (Table_head) {
                                                $$renderer9.push("<!--[-->");
                                                Table_head($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->Source`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_body) {
                                    $$renderer7.push("<!--[-->");
                                    Table_body($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!--[-->`);
                                        const each_array_3 = ensure_array_like(series());
                                        for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                                          let s = each_array_3[$$index_3];
                                          if (Table_row) {
                                            $$renderer8.push("<!--[-->");
                                            Table_row($$renderer8, {
                                              children: ($$renderer9) => {
                                                if (Table_cell) {
                                                  $$renderer9.push("<!--[-->");
                                                  Table_cell($$renderer9, {
                                                    class: "font-medium",
                                                    children: ($$renderer10) => {
                                                      $$renderer10.push(`<!---->${escape_html(s.area_name)}`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
                                                  $$renderer9.push("<!--]-->");
                                                } else {
                                                  $$renderer9.push("<!--[!-->");
                                                  $$renderer9.push("<!--]-->");
                                                }
                                                if (Table_cell) {
                                                  $$renderer9.push("<!--[-->");
                                                  Table_cell($$renderer9, {
                                                    class: "text-right tabular-nums",
                                                    children: ($$renderer10) => {
                                                      $$renderer10.push(`<!---->${escape_html(formatScore(s.score))}`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
                                                  $$renderer9.push("<!--]-->");
                                                } else {
                                                  $$renderer9.push("<!--[!-->");
                                                  $$renderer9.push("<!--]-->");
                                                }
                                                if (Table_cell) {
                                                  $$renderer9.push("<!--[-->");
                                                  Table_cell($$renderer9, {
                                                    class: "text-muted-foreground",
                                                    children: ($$renderer10) => {
                                                      $$renderer10.push(`<!---->${escape_html(s.source ?? "—")}`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
                                                  $$renderer9.push("<!--]-->");
                                                } else {
                                                  $$renderer9.push("<!--[!-->");
                                                  $$renderer9.push("<!--]-->");
                                                }
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (record.tier === "B" && consultationOutcome()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Consultation outcome`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (consultationOutcome().version) {
                            $$renderer6.push("<!--[0-->");
                            if (Card_description) {
                              $$renderer6.push("<!--[-->");
                              Card_description($$renderer6, {
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Evidence version ${escape_html(consultationOutcome().version)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
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
                    $$renderer5.push(` `);
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 pt-4 text-sm",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Decision</span> <span class="font-mono text-xs font-semibold uppercase tracking-wide">${escape_html(consultationOutcome().decision_type ?? "—")}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Recorded by</span> <span class="font-medium">${escape_html(consultationOutcome().recorded_by ?? "—")}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Recorded at</span> <span class="text-muted-foreground">${escape_html(formatDateTime(consultationOutcome().recorded_at))}</span></div> `);
                          if (consultationOutcome().notes) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="border-border rounded-md border bg-muted/30 p-3"><p class="whitespace-pre-wrap text-sm text-foreground">${escape_html(consultationOutcome().notes)}</p></div>`);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (record.tier === "B" && followUps().length > 0) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Follow-ups`);
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
                                $$renderer7.push(`<!---->Open and historical follow-up activity for this applicant.`);
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
                        class: "pt-2",
                        children: ($$renderer6) => {
                          if (Table) {
                            $$renderer6.push("<!--[-->");
                            Table($$renderer6, {
                              class: "w-full text-sm",
                              children: ($$renderer7) => {
                                if (Table_header) {
                                  $$renderer7.push("<!--[-->");
                                  Table_header($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Program`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Status`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Reason`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Opened`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                class: "text-right",
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Actions`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_body) {
                                  $$renderer7.push("<!--[-->");
                                  Table_body($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!--[-->`);
                                      const each_array_4 = ensure_array_like(followUps());
                                      for (let i = 0, $$length = each_array_4.length; i < $$length; i++) {
                                        let row = each_array_4[i];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-mono text-xs",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.program_code ?? "—")}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    Badge($$renderer10, {
                                                      variant: followUpStatusVariant(row.status),
                                                      children: ($$renderer11) => {
                                                        $$renderer11.push(`<!---->${escape_html(followUpStatusLabel(row.status))}`);
                                                      },
                                                      $$slots: { default: true }
                                                    });
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "max-w-[240px]",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<span class="line-clamp-2 text-muted-foreground">${escape_html(row.reason ?? row.resolution_reason ?? "—")}</span>`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "whitespace-nowrap text-muted-foreground",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(formatDateTime(row.opened_at))}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "text-right",
                                                  children: ($$renderer10) => {
                                                    if (row.id) {
                                                      $$renderer10.push("<!--[0-->");
                                                      Button($$renderer10, {
                                                        variant: "ghost",
                                                        size: "sm",
                                                        class: "h-8 px-2 text-xs font-semibold",
                                                        onclick: () => openResolve(row),
                                                        children: ($$renderer11) => {
                                                          $$renderer11.push(`<!---->Resolve`);
                                                        },
                                                        $$slots: { default: true }
                                                      });
                                                    } else {
                                                      $$renderer10.push("<!--[-1-->");
                                                      $$renderer10.push(`<span class="text-xs text-muted-foreground">${escape_html(row.resolved_at ? `Resolved ${formatDateTime(row.resolved_at)}` : "")}</span>`);
                                                    }
                                                    $$renderer10.push(`<!--]-->`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
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
            $$renderer4.push(`<!--]--> <p class="text-xs text-muted-foreground">Record as of ${escape_html(formatDateTime(record.as_of))}. Scores and counselor notes are shown only at the visibility the server granted.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return declineDialogOpen;
              },
              set open($$value) {
                declineDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Decline program selection`);
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
                                  $$renderer8.push(`<!---->Terminal action: releases the applicant's selection for this program and records an immutable decline. Requires a reason.`);
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
                      $$renderer6.push(` <div class="space-y-4"><div class="flex items-center justify-between gap-3 rounded-md border bg-muted/30 px-3 py-2 text-sm"><span class="text-muted-foreground">Program</span> <span class="font-mono text-xs font-semibold">${escape_html(declineForm.program_code || "—")}</span></div> <div class="space-y-2"><label class="text-sm font-medium" for="decline-reason">Reason</label> `);
                      Textarea($$renderer6, {
                        id: "decline-reason",
                        rows: 3,
                        placeholder: "Reason for declining this selection…",
                        get value() {
                          return declineForm.reason;
                        },
                        set value($$value) {
                          declineForm.reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (declineForm.errors?.decline) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs">${escape_html(declineForm.errors.decline)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => declineDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "destructive",
                              disabled: !declineForm.reason.trim() || declineForm.processing,
                              onclick: submitDecline,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(declineForm.processing ? "Declining…" : "Confirm decline")}`);
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
                return resolveDialogOpen;
              },
              set open($$value) {
                resolveDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Resolve follow-up`);
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
                                  $$renderer8.push(`<!---->${escape_html(activeFollowUp ? `${record?.name ?? ""}` : "")} — record the program head's resolution. The server re-checks scope and state.`);
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
                      $$renderer6.push(` <div class="space-y-4"><div class="space-y-2"><label class="text-sm font-medium" for="record-resolve-outcome">Outcome</label> `);
                      if (Select) {
                        $$renderer6.push("<!--[-->");
                        Select($$renderer6, {
                          type: "single",
                          value: resolveForm.outcome,
                          onValueChange: (v) => resolveForm.outcome = v ?? "",
                          children: ($$renderer7) => {
                            if (Select_trigger) {
                              $$renderer7.push("<!--[-->");
                              Select_trigger($$renderer7, {
                                id: "record-resolve-outcome",
                                class: "w-full min-h-[40px]",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(resolveOutcomes.find((o) => o.value === resolveForm.outcome)?.label ?? "Select outcome")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Select_content) {
                              $$renderer7.push("<!--[-->");
                              Select_content($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!--[-->`);
                                  const each_array_5 = ensure_array_like(resolveOutcomes);
                                  for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                                    let outcome = each_array_5[$$index_5];
                                    if (Select_item) {
                                      $$renderer8.push("<!--[-->");
                                      Select_item($$renderer8, { value: outcome.value, label: outcome.label });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
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
                      $$renderer6.push(`</div> <div class="space-y-2"><label class="text-sm font-medium" for="record-resolve-reason">Reason</label> `);
                      Textarea($$renderer6, {
                        id: "record-resolve-reason",
                        rows: 3,
                        placeholder: "Required for cleared or declined outcomes…",
                        get value() {
                          return resolveForm.reason;
                        },
                        set value($$value) {
                          resolveForm.reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> <p class="text-xs text-muted-foreground">Terminal outcomes (cleared / declined) require a reason.</p></div> `);
                      if (resolveForm.errors?.outcome || resolveForm.errors?.reason) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs">${escape_html(resolveForm.errors.outcome ?? resolveForm.errors.reason)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => resolveDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              disabled: resolveReasonRequired() || resolveForm.processing,
                              onclick: submitResolve,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(resolveForm.processing ? "Resolving…" : "Confirm resolution")}`);
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
  });
}
export {
  ApplicantShow as default
};
