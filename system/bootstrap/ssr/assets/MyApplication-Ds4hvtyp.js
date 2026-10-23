import { h as head, e as escape_html, B as Button, a as ensure_array_like, d as derived } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Calendar_clock } from "./calendar-clock-D72LURiz.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
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
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
function MyApplication($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { application = null } = $$props;
    let tourOpen = false;
    const applicationContext = derived(() => {
      const status = application?.status ?? "pending";
      const needsCorr = Boolean(application?.needs_correction);
      const hasG = Boolean(application?.grades?.length > 0);
      const hasP = Boolean(application?.program_preferences?.length > 0);
      const safetyKey = `${status}:${needsCorr ? "corr" : "ok"}:${hasG ? "g" : "nog"}:${hasP ? "p" : "nop"}`;
      return {
        entityKey: application?.reference_code ?? "my-application",
        safetyKey,
        freshness: "current",
        applicationStatus: status,
        needsCorrection: needsCorr,
        correctionNotes: application?.correction_notes ?? null,
        hasGrades: hasG,
        hasPreferences: hasP,
        canAmend: status === "pending" || needsCorr
      };
    });
    const statusLabel = (status) => {
      const labels = {
        pending: "Pending review",
        needs_correction: "Needs your correction",
        accepted: "Accepted",
        dismissed: "Dismissed",
        archived: "Archived"
      };
      return labels[status] ?? status;
    };
    const statusVariant = (status) => {
      if (status === "needs_correction") return "destructive";
      if (status === "accepted") return "default";
      if (status === "dismissed") return "destructive";
      return "secondary";
    };
    const rows = derived(() => application ? [
      { label: "Full name", value: application.personal?.name },
      { label: "Birthdate", value: application.personal?.birthdate },
      {
        label: "Contact phone",
        value: application.personal?.contact_phone
      },
      {
        label: "Contact email",
        value: application.personal?.contact_email
      },
      { label: "Address", value: application.personal?.address },
      {
        label: "Applicant type",
        value: application.personal?.applicant_type
      },
      {
        label: "Last school",
        value: application.personal?.last_school
      },
      { label: "Strand", value: application.personal?.strand },
      { label: "GWA", value: application.personal?.gwa }
    ].filter((row) => row.value) : []);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("5t2gr0", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>My application</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "My application",
        breadcrumbs: [{ label: "My application" }],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="space-y-5">`);
          if (!application) {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "p-6 text-center text-sm text-muted-foreground",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->No application record available.`);
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
            $$renderer4.push(`<div class="space-y-1"><div class="flex flex-wrap items-center gap-3" data-tour="my-application-status"><h1 class="text-2xl font-semibold tracking-tight">My application</h1> `);
            TourTriggerGlyph($$renderer4, {
              tourKey: "applicant-my-application",
              label: "Need help understanding your application? Ask Kuya Xiam!",
              onclick: () => tourOpen = true
            });
            $$renderer4.push(`<!----> `);
            Badge($$renderer4, {
              variant: statusVariant(application.status),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(statusLabel(application.status))}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <p class="font-mono text-sm text-muted-foreground">Ref ${escape_html(application.reference_code)}</p></div> `);
            if (application.needs_correction) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-amber-500/40 bg-amber-500/10",
                  "data-tour": "my-application-correction",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 p-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<p class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">`);
                          Triangle_alert($$renderer6, { class: "size-4", "aria-hidden": "true" });
                          $$renderer6.push(`<!----> Registrar needs a correction</p> `);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                "data-tour": "my-application-details",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b pb-3",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Personal information`);
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
                      class: "pt-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2"><!--[-->`);
                        const each_array = ensure_array_like(rows());
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let row = each_array[$$index];
                          $$renderer6.push(`<div><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">${escape_html(row.label)}</dt> <dd class="mt-0.5 text-sm">${escape_html(row.value)}</dd></div>`);
                        }
                        $$renderer6.push(`<!--]--></dl>`);
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
                "data-tour": "my-application-preferences",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b pb-3",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
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
                      class: "pt-4",
                      children: ($$renderer6) => {
                        if (application.preferences?.length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<ol class="space-y-2"><!--[-->`);
                          const each_array_1 = ensure_array_like(application.preferences);
                          for (let i = 0, $$length = each_array_1.length; i < $$length; i++) {
                            let pref = each_array_1[i];
                            $$renderer6.push(`<li class="flex items-center gap-3 text-sm"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">${escape_html(i + 1)}</span> <span class="font-mono">${escape_html(pref)}</span></li>`);
                          }
                          $$renderer6.push(`<!--]--></ol>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">No preferences recorded.</p>`);
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
            if (application.grades?.length) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  "data-tour": "my-application-grades",
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "border-b pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Intake grades`);
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
                                $$renderer7.push(`<!---->Digital grades from your application (D-086).`);
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
                        class: "pt-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2"><!--[-->`);
                          const each_array_2 = ensure_array_like(application.grades);
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let grade = each_array_2[$$index_2];
                            $$renderer6.push(`<div><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">${escape_html(grade.label)}</dt> <dd class="mt-0.5 font-mono text-sm tabular-nums">${escape_html(grade.value)}</dd></div>`);
                          }
                          $$renderer6.push(`<!--]--></dl>`);
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
            if (application.submitted_at) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "flex items-center gap-3 p-4 text-sm text-muted-foreground",
                        children: ($$renderer6) => {
                          Calendar_clock($$renderer6, { class: "size-4 shrink-0", "aria-hidden": "true" });
                          $$renderer6.push(`<!----> <span>Submitted on <span class="font-medium text-foreground">${escape_html(new Date(application.submitted_at).toLocaleString(void 0, {
                            month: "long",
                            day: "numeric",
                            year: "numeric",
                            hour: "2-digit",
                            minute: "2-digit"
                          }))}</span></span>`);
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
            $$renderer4.push(`<!--]--> <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">`);
            File_text($$renderer4, { class: "mt-0.5 size-3.5 shrink-0", "aria-hidden": "true" });
            $$renderer4.push(`<!----> This is a read-only copy of your submitted application. Changes are only possible when the
        Registrar requests a correction.</p>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-my-application",
            context: applicationContext(),
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
  MyApplication as default
};
