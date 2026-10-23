import { g as sanitize_props, i as spread_props, j as slot, h as head, a as ensure_array_like, e as escape_html } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { H as History } from "./history-D15LFKXr.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "./dialog-description-D12L0QA9.js";
import "./dialog-footer-kNnwKT2h.js";
import "qrcode";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
import "./card-description-BGy_v5-d.js";
import "./shield-alert-CXtDdte9.js";
function Message_square_text($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"
      }
    ],
    ["path", { "d": "M7 11h10" }],
    ["path", { "d": "M7 15h6" }],
    ["path", { "d": "M7 7h8" }]
  ];
  Icon($$renderer, spread_props([
    { name: "message-square-text" },
    $$sanitized_props,
    {
      /**
       * @component @name MessageSquareText
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjIgMTdhMiAyIDAgMCAxLTIgMkg2LjgyOGEyIDIgMCAwIDAtMS40MTQuNTg2bC0yLjIwMiAyLjIwMkEuNzEuNzEgMCAwIDEgMiAyMS4yODZWNWEyIDIgMCAwIDEgMi0yaDE2YTIgMiAwIDAgMSAyIDJ6IiAvPgogIDxwYXRoIGQ9Ik03IDExaDEwIiAvPgogIDxwYXRoIGQ9Ik03IDE1aDYiIC8+CiAgPHBhdGggZD0iTTcgN2g4IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/message-square-text
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
function AdmissionSupport($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", threads = [], access_history = [] } = $$props;
    const eventLabels = {
      program_head_record_viewed: "Program Head viewed assigned-program record",
      "registrar.enrollment-eligibility.viewed": "Registrar opened enrollment eligibility",
      applicant_qr_resolved: "Staff resolved applicant QR"
    };
    function eventLabel(type) {
      return eventLabels[type] ?? type;
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
    function threadStatusVariant(status) {
      return status === "open" ? "default" : "secondary";
    }
    head("14maywo", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Admission support</title>`);
      });
    });
    ApplicantLayout($$renderer2, {
      title: "Admission support",
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="mx-auto max-w-3xl space-y-6">`);
        if (page_state !== "ready") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="sr-only">`);
          E2PageState($$renderer3, { state: page_state });
          $$renderer3.push(`<!----></div> `);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              children: ($$renderer4) => {
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "space-y-3 p-6 text-center",
                    children: ($$renderer5) => {
                      Shield_check($$renderer5, { class: "mx-auto size-8 text-muted-foreground" });
                      $$renderer5.push(`<!----> <p class="font-medium">Admission support unavailable</p> <p class="text-sm text-muted-foreground">This account cannot access admission support.</p>`);
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
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="space-y-1"><h1 class="text-2xl font-semibold tracking-tight">Admission support</h1> <p class="text-sm text-muted-foreground">Messages with staff, and who has viewed your admission records.</p></div> <section class="space-y-3"><div class="flex items-center gap-2">`);
          Message_square_text($$renderer3, { class: "size-4 text-muted-foreground" });
          $$renderer3.push(`<!----> <h2 class="text-lg font-semibold tracking-tight">Support threads</h2></div> `);
          if (threads.length === 0) {
            $$renderer3.push("<!--[0-->");
            if (Card) {
              $$renderer3.push("<!--[-->");
              Card($$renderer3, {
                children: ($$renderer4) => {
                  if (Card_content) {
                    $$renderer4.push("<!--[-->");
                    Card_content($$renderer4, {
                      class: "space-y-2 p-6 text-center",
                      children: ($$renderer5) => {
                        Message_square_text($$renderer5, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer5.push(`<!----> <p class="font-medium">No support threads yet</p> <p class="text-sm text-muted-foreground">When staff message you about your admission, the conversation appears here.</p>`);
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
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<!--[-->`);
            const each_array = ensure_array_like(threads);
            for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
              let thread = each_array[$$index_1];
              if (Card) {
                $$renderer3.push("<!--[-->");
                Card($$renderer3, {
                  class: "overflow-hidden shadow-sm",
                  children: ($$renderer4) => {
                    if (Card_header) {
                      $$renderer4.push("<!--[-->");
                      Card_header($$renderer4, {
                        class: "border-b bg-muted/20 pb-3",
                        children: ($$renderer5) => {
                          $$renderer5.push(`<div class="flex items-start justify-between gap-3"><div class="min-w-0 space-y-1">`);
                          if (Card_title) {
                            $$renderer5.push("<!--[-->");
                            Card_title($$renderer5, {
                              class: "text-base",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->${escape_html(thread.subject)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer5.push("<!--]-->");
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push("<!--]-->");
                          }
                          $$renderer5.push(` `);
                          if (thread.status !== "open") {
                            $$renderer5.push("<!--[0-->");
                            $$renderer5.push(`<p class="text-xs text-muted-foreground">Read-only — this thread is no longer open for replies.</p>`);
                          } else {
                            $$renderer5.push("<!--[-1-->");
                          }
                          $$renderer5.push(`<!--]--></div> `);
                          Badge($$renderer5, {
                            variant: threadStatusVariant(thread.status),
                            class: "shrink-0",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->${escape_html(thread.status)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push(`<!----></div>`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer4.push("<!--]-->");
                    } else {
                      $$renderer4.push("<!--[!-->");
                      $$renderer4.push("<!--]-->");
                    }
                    $$renderer4.push(` `);
                    if (Card_content) {
                      $$renderer4.push("<!--[-->");
                      Card_content($$renderer4, {
                        class: "space-y-4 pt-4",
                        children: ($$renderer5) => {
                          $$renderer5.push(`<!--[-->`);
                          const each_array_1 = ensure_array_like(thread.messages);
                          for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                            let message = each_array_1[$$index];
                            $$renderer5.push(`<div class="space-y-1"><div class="flex items-baseline justify-between gap-3"><p class="text-sm font-medium">${escape_html(message.sender ?? "Staff")}</p> <p class="text-xs text-muted-foreground">${escape_html(formatDateTime(message.created_at))}</p></div> <p class="whitespace-pre-line text-sm text-muted-foreground">${escape_html(message.body)}</p></div>`);
                          }
                          $$renderer5.push(`<!--]-->`);
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
            $$renderer3.push(`<!--]-->`);
          }
          $$renderer3.push(`<!--]--></section> <section class="space-y-3"><div class="flex items-center gap-2">`);
          History($$renderer3, { class: "size-4 text-muted-foreground" });
          $$renderer3.push(`<!----> <h2 class="text-lg font-semibold tracking-tight">Access history</h2></div> `);
          if (access_history.length === 0) {
            $$renderer3.push("<!--[0-->");
            if (Card) {
              $$renderer3.push("<!--[-->");
              Card($$renderer3, {
                children: ($$renderer4) => {
                  if (Card_content) {
                    $$renderer4.push("<!--[-->");
                    Card_content($$renderer4, {
                      class: "space-y-2 p-6 text-center",
                      children: ($$renderer5) => {
                        Shield_check($$renderer5, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer5.push(`<!----> <p class="font-medium">No access recorded yet</p> <p class="text-sm text-muted-foreground">Each time staff open your admission records, the visit is listed here.</p>`);
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
          } else {
            $$renderer3.push("<!--[-1-->");
            if (Card) {
              $$renderer3.push("<!--[-->");
              Card($$renderer3, {
                class: "overflow-hidden shadow-sm",
                children: ($$renderer4) => {
                  if (Card_content) {
                    $$renderer4.push("<!--[-->");
                    Card_content($$renderer4, {
                      class: "p-0",
                      children: ($$renderer5) => {
                        if (Table) {
                          $$renderer5.push("<!--[-->");
                          Table($$renderer5, {
                            children: ($$renderer6) => {
                              if (Table_header) {
                                $$renderer6.push("<!--[-->");
                                Table_header($$renderer6, {
                                  children: ($$renderer7) => {
                                    if (Table_row) {
                                      $$renderer7.push("<!--[-->");
                                      Table_row($$renderer7, {
                                        children: ($$renderer8) => {
                                          if (Table_head) {
                                            $$renderer8.push("<!--[-->");
                                            Table_head($$renderer8, {
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->Who`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                          $$renderer8.push(` `);
                                          if (Table_head) {
                                            $$renderer8.push("<!--[-->");
                                            Table_head($$renderer8, {
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->Action`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer8.push("<!--]-->");
                                          } else {
                                            $$renderer8.push("<!--[!-->");
                                            $$renderer8.push("<!--]-->");
                                          }
                                          $$renderer8.push(` `);
                                          if (Table_head) {
                                            $$renderer8.push("<!--[-->");
                                            Table_head($$renderer8, {
                                              class: "text-right",
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->When`);
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
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                              $$renderer6.push(` `);
                              if (Table_body) {
                                $$renderer6.push("<!--[-->");
                                Table_body($$renderer6, {
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!--[-->`);
                                    const each_array_2 = ensure_array_like(access_history);
                                    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                                      let event = each_array_2[$$index_2];
                                      if (Table_row) {
                                        $$renderer7.push("<!--[-->");
                                        Table_row($$renderer7, {
                                          children: ($$renderer8) => {
                                            if (Table_cell) {
                                              $$renderer8.push("<!--[-->");
                                              Table_cell($$renderer8, {
                                                class: "font-medium",
                                                children: ($$renderer9) => {
                                                  $$renderer9.push(`<!---->${escape_html(event.actor ?? "Staff")}`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer8.push("<!--]-->");
                                            } else {
                                              $$renderer8.push("<!--[!-->");
                                              $$renderer8.push("<!--]-->");
                                            }
                                            $$renderer8.push(` `);
                                            if (Table_cell) {
                                              $$renderer8.push("<!--[-->");
                                              Table_cell($$renderer8, {
                                                class: "text-muted-foreground",
                                                children: ($$renderer9) => {
                                                  $$renderer9.push(`<!---->${escape_html(eventLabel(event.event_type))}`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer8.push("<!--]-->");
                                            } else {
                                              $$renderer8.push("<!--[!-->");
                                              $$renderer8.push("<!--]-->");
                                            }
                                            $$renderer8.push(` `);
                                            if (Table_cell) {
                                              $$renderer8.push("<!--[-->");
                                              Table_cell($$renderer8, {
                                                class: "text-right text-muted-foreground",
                                                children: ($$renderer9) => {
                                                  $$renderer9.push(`<!---->${escape_html(formatDateTime(event.occurred_at))}`);
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
          $$renderer3.push(`<!--]--></section>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  AdmissionSupport as default
};
