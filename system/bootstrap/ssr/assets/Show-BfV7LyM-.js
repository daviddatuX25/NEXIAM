import { h as head, e as escape_html, B as Button, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import IntakeCorrectionFields from "./IntakeCorrectionFields-BT17XBCB.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { C as Circle_check_big } from "./ToastManager-C7oO_4Jn.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
import "./Icon-Dal7PqKK.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./triangle-alert-CUvuB-HL.js";
import "./AvatarBadge-DQ5Qru9A.js";
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
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./x-CnxrMbMn.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./PhilippineAddressSelector-UGmHumOi.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./info-C3MCzlQW.js";
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      attempt,
      programs = [],
      grade_fields = [],
      reissued_access_key = null,
      capabilities = { accept: false, dismiss: false, correct: false }
    } = $$props;
    let dismissOpen = false;
    let requestOpen = false;
    let archiveOpen = false;
    let reopenOpen = false;
    let editOpen = false;
    let amendOpen = false;
    let reconsiderOpen = false;
    let reissueOpen = false;
    let keyVisible = false;
    let dismissReason = "";
    let requestReason = "";
    let reopenReason = "";
    let reissueReason = "";
    let reconsiderReason = "";
    let amendReason = "";
    const initialGrades = Object.fromEntries((grade_fields ?? []).map((f) => [
      f.code,
      attempt.grades?.find((g) => g.code === f.code)?.value ?? ""
    ]));
    const editForm = useForm({
      expected_version: attempt.version,
      applicant_type: attempt.applicant_type ?? "new",
      contact_email: attempt.contact_email ?? "",
      contact_phone: attempt.contact_phone ?? "",
      last_school_enrolled: attempt.last_school_enrolled ?? "",
      strand: attempt.strand ?? "",
      grades: initialGrades,
      program_preference_1_code: attempt.program_preferences?.[0] ?? attempt.preferred_program_code ?? "",
      program_preference_2_code: attempt.program_preferences?.[1] ?? "",
      program_preference_3_code: attempt.program_preferences?.[2] ?? "",
      address_line: attempt.address?.line ?? "",
      city: attempt.address?.city ?? "",
      province: attempt.address?.province ?? "",
      zip_code: attempt.address?.zip_code ?? "",
      correction_notes: attempt.correction_notes ?? ""
    });
    const amendForm = useForm({
      expected_version: attempt.version,
      reason: "",
      identity_first_name: attempt.identity?.first_name ?? "",
      identity_middle_name: attempt.identity?.middle_name ?? "",
      identity_last_name: attempt.identity?.last_name ?? "",
      identity_suffix: attempt.identity?.suffix ?? "",
      identity_birthdate: attempt.identity?.birthdate ?? "",
      identity_sex: attempt.identity?.sex ?? "male",
      identity_email: attempt.identity?.email ?? "",
      identity_phone: attempt.identity?.phone ?? "",
      applicant_type: attempt.applicant_type ?? "new",
      contact_email: attempt.contact_email ?? "",
      contact_phone: attempt.contact_phone ?? "",
      last_school_enrolled: attempt.last_school_enrolled ?? "",
      strand: attempt.strand ?? "",
      grades: { ...initialGrades },
      program_preference_1_code: attempt.program_preferences?.[0] ?? attempt.preferred_program_code ?? "",
      program_preference_2_code: attempt.program_preferences?.[1] ?? "",
      program_preference_3_code: attempt.program_preferences?.[2] ?? "",
      address_line: attempt.address?.line ?? "",
      city: attempt.address?.city ?? "",
      province: attempt.address?.province ?? "",
      zip_code: attempt.address?.zip_code ?? ""
    });
    const canDecide = derived(() => attempt.status === "pending" || attempt.status === "needs_correction");
    const canRequestCorrection = derived(() => attempt.status === "pending" && capabilities.correct);
    const canEditFields = derived(() => canDecide() && capabilities.correct);
    const isAccepted = derived(() => attempt.status === "accepted");
    const canAuthorizeAmend = derived(() => isAccepted() && capabilities.accept);
    const canReconsider = derived(() => isAccepted() && capabilities.accept);
    const canRevert = derived(() => (attempt.status === "dismissed" || attempt.status === "archived") && capabilities.accept);
    const canArchive = derived(() => attempt.status !== "archived" && capabilities.dismiss);
    const canReissueKey = derived(() => attempt.status !== "archived" && capabilities.correct);
    const addressLine = derived(() => [
      attempt.address?.line,
      attempt.address?.city,
      attempt.address?.province,
      attempt.address?.zip_code
    ].filter(Boolean).join(", ") || "—");
    const preferenceList = derived(() => attempt.program_preferences?.length ? attempt.program_preferences : attempt.preferred_program_code ? [attempt.preferred_program_code] : []);
    function statusVariant(status) {
      switch (status) {
        case "accepted":
          return "default";
        case "needs_correction":
          return "outline";
        case "dismissed":
          return "destructive";
        default:
          return "secondary";
      }
    }
    function statusLabel(status) {
      const labels = {
        pending: "Pending",
        needs_correction: "Needs correction",
        accepted: "Accepted",
        dismissed: "Dismissed",
        archived: "Archived"
      };
      return labels[status] ?? status;
    }
    function formatDate(iso) {
      if (!iso) return "—";
      try {
        return new Date(iso).toLocaleDateString(void 0, { year: "numeric", month: "short", day: "numeric" });
      } catch {
        return iso;
      }
    }
    function formatDateTime(iso) {
      if (!iso) return "—";
      try {
        return new Date(iso).toLocaleString(void 0, {
          year: "numeric",
          month: "short",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit"
        });
      } catch {
        return iso;
      }
    }
    function accept() {
      router.post(`/admissions/${attempt.id}/accept`, { expected_version: attempt.version });
    }
    function submitDismiss() {
      if (!dismissReason.trim()) return;
      router.post(
        `/admissions/${attempt.id}/dismiss`,
        {
          expected_version: attempt.version,
          reason: dismissReason.trim()
        },
        {
          onSuccess: () => {
            dismissOpen = false;
          }
        }
      );
    }
    function submitRequestCorrection() {
      if (!requestReason.trim()) return;
      router.post(
        `/admissions/${attempt.id}/request-correction`,
        {
          expected_version: attempt.version,
          reason: requestReason.trim()
        },
        {
          onSuccess: () => {
            requestOpen = false;
          }
        }
      );
    }
    function submitArchive() {
      router.post(`/admissions/${attempt.id}/archive`, { expected_version: attempt.version }, {
        onSuccess: () => {
          archiveOpen = false;
        }
      });
    }
    function submitReopen() {
      if (!reopenReason.trim()) return;
      router.post(
        `/admissions/${attempt.id}/reopen`,
        {
          expected_version: attempt.version,
          reason: reopenReason.trim()
        },
        {
          onSuccess: () => {
            reopenOpen = false;
          }
        }
      );
    }
    function submitEdit() {
      editForm.expected_version = attempt.version;
      editForm.post(`/admissions/${attempt.id}/correct`, {
        onSuccess: () => {
          editOpen = false;
        }
      });
    }
    function submitAmend() {
      if (!amendReason.trim()) return;
      amendForm.expected_version = attempt.version;
      amendForm.reason = amendReason.trim();
      amendForm.post(`/admissions/${attempt.id}/authorize-amendment`, {
        onSuccess: () => {
          amendOpen = false;
        }
      });
    }
    function submitReconsider() {
      if (!reconsiderReason.trim()) return;
      router.post(
        `/admissions/${attempt.id}/reconsider`,
        {
          expected_version: attempt.version,
          reason: reconsiderReason.trim()
        },
        {
          onSuccess: () => {
            reconsiderOpen = false;
          }
        }
      );
    }
    function submitReissue() {
      router.post(
        `/admissions/${attempt.id}/reissue-access-key`,
        {
          expected_version: attempt.version,
          reason: reissueReason.trim()
        },
        {
          onSuccess: () => {
            reissueOpen = false;
          }
        }
      );
    }
    const breadcrumbs = [
      { label: "Admissions", href: "/admissions" },
      { label: attempt.reference_code ?? "Application" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1i0hrzg", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(attempt.reference_code)} · Admissions</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Badge($$renderer5, {
                variant: statusVariant(attempt.status),
                class: "capitalize text-sm",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->${escape_html(statusLabel(attempt.status))}`);
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: attempt.applicant_name,
              description: `Reference ${attempt.reference_code} · v${attempt.version} · ${attempt.cycle?.name ?? "Cycle —"}`,
              actions
            });
          }
          $$renderer4.push(`<!----> <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 shadow-[0_1px_2px_oklch(0_0_0/0.04)]"><div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"><span>Status</span> `);
          Badge($$renderer4, {
            variant: statusVariant(attempt.status),
            class: "capitalize",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(statusLabel(attempt.status))}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          if (attempt.cycle?.code) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<span class="text-xs">${escape_html(attempt.cycle.code)}</span>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-2">`);
          if (capabilities.accept && canDecide()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              class: "min-h-10",
              onclick: accept,
              children: ($$renderer5) => {
                Circle_check_big($$renderer5, { class: "mr-1.5 size-4" });
                $$renderer5.push(`<!----> Accept`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (capabilities.dismiss && canDecide()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              class: "min-h-10 border-destructive/30 text-destructive hover:bg-destructive/5",
              onclick: () => {
                dismissReason = "";
                dismissOpen = true;
              },
              children: ($$renderer5) => {
                Circle_x($$renderer5, { class: "mr-1.5 size-4" });
                $$renderer5.push(`<!----> Dismiss`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canRequestCorrection()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              class: "min-h-10",
              onclick: () => {
                requestReason = "";
                requestOpen = true;
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Ask applicant to correct`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canEditFields()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "ghost",
              class: "min-h-10",
              onclick: () => editOpen = true,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Update for applicant`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canAuthorizeAmend()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              class: "min-h-10",
              onclick: () => {
                amendReason = "";
                amendOpen = true;
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Authorize amendment`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canReconsider()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              class: "min-h-10",
              onclick: () => {
                reconsiderReason = "";
                reconsiderOpen = true;
              },
              children: ($$renderer5) => {
                Rotate_ccw($$renderer5, { class: "mr-1.5 size-4" });
                $$renderer5.push(`<!----> Reconsider acceptance`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canRevert()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              class: "min-h-10",
              onclick: () => {
                reopenReason = "";
                reopenOpen = true;
              },
              children: ($$renderer5) => {
                Rotate_ccw($$renderer5, { class: "mr-1.5 size-4" });
                $$renderer5.push(`<!----> Revert to pending`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canArchive()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "ghost",
              class: "min-h-10 text-muted-foreground",
              onclick: () => archiveOpen = true,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Archive`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canReissueKey()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              size: "sm",
              variant: "ghost",
              class: "min-h-10",
              onclick: () => {
                reissueReason = "";
                reissueOpen = true;
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Reissue access key`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></div> `);
          if (reissued_access_key) {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-primary/20",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b border-border/60 pb-4",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-base",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->New access key`);
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
                              $$renderer7.push(`<!---->Copy now — shown once. Prior keys are revoked.`);
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
                      class: "space-y-3 pt-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-wrap items-center gap-2">`);
                        Input($$renderer6, {
                          class: "max-w-xs font-mono tracking-widest",
                          type: keyVisible ? "text" : "password",
                          readonly: true,
                          value: reissued_access_key
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          size: "sm",
                          variant: "outline",
                          onclick: () => keyVisible = !keyVisible,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(keyVisible ? "Hide" : "Show")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          size: "sm",
                          variant: "outline",
                          onclick: async () => {
                            try {
                              await navigator.clipboard.writeText(reissued_access_key);
                            } catch {
                            }
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Copy`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div> <p class="text-xs text-muted-foreground">Give this key to the applicant with their birthdate for portal login.</p>`);
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
          if (isAccepted()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">Accepted — exam-eligible. Quiet Update / Ask correction stay off.
        Use <span class="font-medium text-foreground">Authorize amendment</span> for wrong name/details
        (stays accepted, no retake). Use <span class="font-medium text-foreground">Reconsider acceptance</span> only if the decision
        itself must return to pending.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (attempt.status === "needs_correction" && attempt.correction_notes) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">Waiting on applicant: ${escape_html(attempt.correction_notes)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (attempt.status === "dismissed" && attempt.dismissal_reason) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">Dismissal reason: ${escape_html(attempt.dismissal_reason)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="grid gap-6 md:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    class: "border-b border-border/60 pb-4",
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-base",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Personal details`);
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
                    class: "space-y-2.5 pt-4 text-sm",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p><span class="text-muted-foreground">Name:</span> ${escape_html(attempt.applicant_name)}</p> <p><span class="text-muted-foreground">Birthdate:</span> ${escape_html(formatDate(attempt.identity?.birthdate))}</p> <p><span class="text-muted-foreground">Sex:</span> <span class="capitalize">${escape_html(attempt.identity?.sex ?? "—")}</span></p> <p><span class="text-muted-foreground">Classification:</span> <span class="capitalize">${escape_html(attempt.applicant_type === "transferee" ? "Transferee" : attempt.applicant_type ?? "New student")}</span></p> `);
                      if (attempt.last_school_enrolled) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">Last school enrolled:</span> ${escape_html(attempt.last_school_enrolled)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.strand) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">SHS strand / previous course:</span> ${escape_html(attempt.strand)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <p><span class="text-muted-foreground">Email:</span> ${escape_html(attempt.contact_email ?? "—")}</p> <p><span class="text-muted-foreground">Phone:</span> ${escape_html(attempt.contact_phone ?? "—")}</p> <p><span class="text-muted-foreground">Address:</span> ${escape_html(addressLine())}</p>`);
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
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    class: "border-b border-border/60 pb-4",
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-base",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Program preferences`);
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
                    class: "space-y-2.5 pt-4 text-sm",
                    children: ($$renderer6) => {
                      if (preferenceList().length) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<!--[-->`);
                        const each_array = ensure_array_like(preferenceList());
                        for (let i = 0, $$length = each_array.length; i < $$length; i++) {
                          let code = each_array[i];
                          $$renderer6.push(`<p><span class="text-muted-foreground">${escape_html(i === 0 ? "1st" : i === 1 ? "2nd" : `${i + 1}th`)}:</span> <span class="font-medium">${escape_html(code ?? "—")}</span></p>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<p class="text-muted-foreground">No preferences recorded.</p>`);
                      }
                      $$renderer6.push(`<!--]--> <p class="pt-2 text-xs text-muted-foreground">Cycle: ${escape_html(attempt.cycle?.name ?? "—")} (${escape_html(attempt.cycle?.code ?? "—")})</p>`);
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
          $$renderer4.push(`</div> <div class="grid gap-6 md:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    class: "border-b border-border/60 pb-4",
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-base",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Grades &amp; consent`);
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
                    class: "space-y-2.5 pt-4 text-sm",
                    children: ($$renderer6) => {
                      if (attempt.grades?.length) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<!--[-->`);
                        const each_array_1 = ensure_array_like(attempt.grades);
                        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                          let g = each_array_1[$$index_1];
                          $$renderer6.push(`<p><span class="text-muted-foreground">${escape_html(g.code)}:</span> <span class="font-mono">${escape_html(g.value)}</span></p>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<p class="text-muted-foreground">No catalog grades on this attempt.</p>`);
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.gwa) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">GWA:</span> <span class="font-mono">${escape_html(attempt.gwa)}</span></p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.privacy_policy_title) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="pt-1 text-xs text-muted-foreground">Consent: ${escape_html(attempt.privacy_policy_title)}</p>`);
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
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    class: "border-b border-border/60 pb-4",
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          class: "text-base",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Timeline`);
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
                    class: "space-y-2.5 pt-4 text-sm",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p><span class="text-muted-foreground">Submitted:</span> ${escape_html(formatDateTime(attempt.submitted_at))}</p> `);
                      if (attempt.accepted_at) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">Accepted:</span> ${escape_html(formatDateTime(attempt.accepted_at))}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.dismissed_at) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">Dismissed:</span> ${escape_html(formatDateTime(attempt.dismissed_at))}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.archived_at) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">Archived:</span> ${escape_html(formatDateTime(attempt.archived_at))}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (attempt.status === "dismissed" && attempt.dismissal_reason) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p><span class="text-muted-foreground">Dismissal reason:</span> ${escape_html(attempt.dismissal_reason)}</p>`);
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
          $$renderer4.push(`</div></div>`);
        }
      });
      $$renderer3.push(`<!----> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return dismissOpen;
          },
          set open($$value) {
            dismissOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Dismiss application`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Requires a reason. History is retained.`);
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
                  $$renderer5.push(` <div class="space-y-2 py-2">`);
                  Label($$renderer5, {
                    for: "dismiss-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Reason`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "dismiss-reason",
                    rows: 3,
                    class: "min-h-24",
                    get value() {
                      return dismissReason;
                    },
                    set value($$value) {
                      dismissReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => dismissOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          variant: "destructive",
                          disabled: !dismissReason.trim(),
                          onclick: submitDismiss,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Dismiss`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return requestOpen;
          },
          set open($$value) {
            requestOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Ask applicant to correct`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Moves status to needs correction and records instructions for the applicant (notice delivery
        later).`);
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
                  $$renderer5.push(` <div class="space-y-2 py-2">`);
                  Label($$renderer5, {
                    for: "request-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->What must be corrected?`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "request-reason",
                    rows: 4,
                    class: "min-h-28",
                    get value() {
                      return requestReason;
                    },
                    set value($$value) {
                      requestReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => requestOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          disabled: !requestReason.trim(),
                          onclick: submitRequestCorrection,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Send request`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return archiveOpen;
          },
          set open($$value) {
            archiveOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Archive application`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Removes it from the active queue. Identity and history are kept.`);
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
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => archiveOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          onclick: submitArchive,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Archive`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return reopenOpen;
          },
          set open($$value) {
            reopenOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Revert to pending`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Non-destructive reopen — never deletes identity or history.`);
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
                  $$renderer5.push(` <div class="space-y-2 py-2">`);
                  Label($$renderer5, {
                    for: "reopen-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Reason`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "reopen-reason",
                    rows: 3,
                    class: "min-h-24",
                    get value() {
                      return reopenReason;
                    },
                    set value($$value) {
                      reopenReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => reopenOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          disabled: !reopenReason.trim(),
                          onclick: submitReopen,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Revert`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return editOpen;
          },
          set open($$value) {
            editOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "flex max-h-[90vh] flex-col sm:max-w-2xl",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Update for applicant`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              if (attempt.status === "needs_correction") {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`Records the office fix from the applicant visit and returns this application to pending
          review (ready for Accept). Audited — does not require portal resubmit.`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                                $$renderer7.push(`Quiet in-place fix while still pending. Stays in the review queue. Same intake fields as
          apply. After accept, use an authorized authority path instead.`);
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
                  $$renderer5.push(` <div class="max-h-[65vh] overflow-y-auto py-2 pr-1">`);
                  IntakeCorrectionFields($$renderer5, {
                    form: editForm,
                    programs,
                    grade_fields,
                    identity: attempt.identity,
                    showInternalNotes: true,
                    idPrefix: "edit"
                  });
                  $$renderer5.push(`<!----> `);
                  if (editForm.errors?.version) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p class="mt-3 text-sm text-destructive">${escape_html(editForm.errors.version)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => editOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          disabled: editForm.processing,
                          onclick: submitEdit,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Save`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return amendOpen;
          },
          set open($$value) {
            amendOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "flex max-h-[90vh] flex-col sm:max-w-2xl",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Authorize amendment`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Fix wrong name or details while staying accepted. Exam assignment and Applicant No stay —
        no retake. Requires an authority reason. Distinct from Reconsider (which pulls acceptance
        back to pending).`);
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
                  $$renderer5.push(` <div class="max-h-[65vh] space-y-4 overflow-y-auto py-2 pr-1"><div class="space-y-2">`);
                  Label($$renderer5, {
                    for: "amend-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Authority reason`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "amend-reason",
                    rows: 2,
                    class: "min-h-20",
                    get value() {
                      return amendReason;
                    },
                    set value($$value) {
                      amendReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  IntakeCorrectionFields($$renderer5, {
                    form: amendForm,
                    programs,
                    grade_fields,
                    identity: attempt.identity,
                    identityEditable: true,
                    idPrefix: "amend"
                  });
                  $$renderer5.push(`<!----> `);
                  if (amendForm.errors?.version) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p class="text-sm text-destructive">${escape_html(amendForm.errors.version)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (amendForm.errors?.reason) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p class="text-sm text-destructive">${escape_html(amendForm.errors.reason)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => amendOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          disabled: amendForm.processing || !amendReason.trim(),
                          onclick: submitAmend,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Record authorized amendment`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return reconsiderOpen;
          },
          set open($$value) {
            reconsiderOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Reconsider acceptance`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Returns status to pending (decision pullback). Existing exam links are preserved for
        reconciliation — new scheduling requires Accept again. Do not use this for a typo; use
        Authorize amendment instead.`);
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
                  $$renderer5.push(` <div class="space-y-2 py-2">`);
                  Label($$renderer5, {
                    for: "reconsider-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Authority reason`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "reconsider-reason",
                    rows: 3,
                    class: "min-h-24",
                    get value() {
                      return reconsiderReason;
                    },
                    set value($$value) {
                      reconsiderReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => reconsiderOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          disabled: !reconsiderReason.trim(),
                          onclick: submitReconsider,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Reconsider`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return reissueOpen;
          },
          set open($$value) {
            reissueOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Reissue access key`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Revokes every prior key for this applicant. The new key is shown once on this page.`);
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
                  $$renderer5.push(` <div class="space-y-2 py-2">`);
                  Label($$renderer5, {
                    for: "reissue-reason",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Reason (optional)`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Textarea($$renderer5, {
                    id: "reissue-reason",
                    rows: 2,
                    class: "min-h-20",
                    get value() {
                      return reissueReason;
                    },
                    set value($$value) {
                      reissueReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          onclick: () => reissueOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          onclick: submitReissue,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Reissue key`);
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
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
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
  Show as default
};
