import { o as onDestroy, h as head, a as ensure_array_like, e as escape_html, B as Button, b as attr, c as attr_class, A as clsx, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { G as GovernanceDiscussionPanel } from "./GovernanceDiscussionPanel-B64Kd0LL.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./textarea-Bv3Na1wO.js";
import "./governanceRequests-C1KnF59a.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Quotas($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      cycle = null,
      cycles = [],
      quota_projections = [],
      registrar_grants = [],
      registrar_candidates = [],
      can_manage_registrar_grants = false,
      is_program_head = false
    } = $$props;
    let selectedProgramId = "";
    let mobileView = "quota";
    let panelView = "discussion";
    let contextLoading = false;
    let contextError = "";
    let grantRecipient = "";
    let grantReason = "";
    let grantBusy = false;
    let grantDenied = false;
    let grantReceipts = {};
    let grantController = null;
    const row = derived(() => quota_projections.find((item) => String(item.program.id) === selectedProgramId) ?? quota_projections[0] ?? null);
    const scopedGrants = derived(() => {
      if (!row() || !cycle) return [];
      const records = new Map(registrar_grants.filter((grant) => String(grant.program_id) === String(row().program.id) && String(grant.cycle_id) === String(cycle.id)).map((grant) => [String(grant.recipient_id), grant]));
      for (const receipt of Object.values(grantReceipts)) {
        if (String(receipt.program_id) === String(row().program.id) && String(receipt.cycle_id) === String(cycle.id)) {
          const current = records.get(String(receipt.recipient_id));
          if (!current || receipt.version > current.version) records.set(String(receipt.recipient_id), receipt);
        }
      }
      return [...records.values()];
    });
    const selectedGrant = derived(() => scopedGrants().find((grant) => String(grant.recipient_id) === grantRecipient) ?? null);
    const form = useForm({ program_id: "", academic_cycle_id: "", slots: "", notice: "" });
    onDestroy(() => grantController?.abort());
    function quotaUrl() {
      return typeof route === "function" ? route("setup.quotas") : "/setup/quotas";
    }
    function changeCycle(value) {
      if (contextLoading || grantBusy || form.processing) return;
      contextError = "";
      router.get(
        quotaUrl(),
        {
          cycle_id: value,
          ...row() ? { program_id: row().program.id } : {}
        },
        {
          preserveState: true,
          preserveScroll: true,
          onStart: () => {
            contextLoading = true;
          },
          onError: () => {
            contextError = "This academic year and semester could not be opened. Select an authorized context and try again.";
          },
          onFinish: () => {
            contextLoading = false;
          }
        }
      );
    }
    function showView(value) {
      mobileView = value;
      if (value !== "quota") panelView = value;
    }
    function formatTime(value) {
      if (!value) return "Time not recorded";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }
    function refreshContext() {
      if (contextLoading || grantBusy || form.processing) return;
      router.reload({
        only: [
          "cycle",
          "cycles",
          "quota_projections",
          "registrar_grants",
          "registrar_candidates",
          "can_manage_registrar_grants"
        ],
        preserveScroll: true,
        onStart: () => {
          contextLoading = true;
          contextError = "";
        },
        onSuccess: () => {
          grantDenied = false;
        },
        onError: () => {
          contextError = "The context could not be refreshed. Your unsaved input is still here.";
        },
        onFinish: () => {
          contextLoading = false;
        }
      });
    }
    const breadcrumbs = [{ label: "Setup", href: "/setup" }, { label: "Quotas" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("si1oc", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Quotas · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    size: "sm",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Back to readiness`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Program quotas",
              description: "Capacity, commitments and quota discussions for your authorized programs.",
              actions
            });
          }
          $$renderer4.push(`<!----> <div class="flex flex-wrap items-end gap-3"><div class="min-w-0 flex-1 space-y-1.5 sm:max-w-sm">`);
          Label($$renderer4, {
            for: "quota-cycle",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Academic year + semester`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "quota-cycle",
              value: cycle ? String(cycle.id) : "",
              onchange: (event) => changeCycle(event.currentTarget.value),
              disabled: contextLoading || form.processing || grantBusy || !cycles.length,
              class: "flex min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
            },
            ($$renderer5) => {
              if (!cycle) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`Select an academic year + semester`);
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--><!--[-->`);
              const each_array = ensure_array_like(cycles);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let item = each_array[$$index];
                $$renderer5.option({ value: String(item.id) }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(item.staff_label || item.name || item.code)}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(`</div> <div class="min-w-0 flex-1 space-y-1.5 sm:max-w-md">`);
          Label($$renderer4, {
            for: "quota-program",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Program`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "quota-program",
              value: row() ? String(row().program.id) : "",
              onchange: (event) => {
                selectedProgramId = event.currentTarget.value;
              },
              disabled: contextLoading || form.processing || grantBusy || !quota_projections.length,
              class: "flex min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
            },
            ($$renderer5) => {
              if (!row()) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`No authorized program`);
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--><!--[-->`);
              const each_array_1 = ensure_array_like(quota_projections);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let item = each_array_1[$$index_1];
                $$renderer5.option({ value: String(item.program.id) }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(item.program.code)} — ${escape_html(item.program.name)}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(`</div> `);
          Button($$renderer4, {
            variant: "outline",
            disabled: contextLoading || form.processing || grantBusy,
            onclick: refreshContext,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(contextLoading ? "Refreshing…" : "Refresh facts")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> `);
          if (contextError) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(contextError)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (!cycle) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">Select an authorized academic year and semester to view quotas.</p>`);
          } else if (!row()) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<p class="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">No programs are available in your current quota scope.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section aria-labelledby="current-quotas-heading"${attr("aria-busy", contextLoading)} class="min-w-0"><h2 id="current-quotas-heading" class="mb-2 text-sm font-semibold">Current quotas · ${escape_html(cycle.staff_label || cycle.name)}</h2> <div class="min-w-0 overflow-x-auto rounded-xl border border-border">`);
            if (Table) {
              $$renderer4.push("<!--[-->");
              Table($$renderer4, {
                class: "min-w-[640px]",
                children: ($$renderer5) => {
                  if (Table_header) {
                    $$renderer5.push("<!--[-->");
                    Table_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Program`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Capacity`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Committed`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Remaining`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
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
                              if (Table_head) {
                                $$renderer7.push("<!--[-->");
                                Table_head($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Context`);
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
                        const each_array_2 = ensure_array_like(quota_projections);
                        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                          let item = each_array_2[$$index_2];
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              class: String(item.program.id) === String(row().program.id) ? "bg-muted/40" : "",
                              children: ($$renderer7) => {
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<span class="font-medium">${escape_html(item.program.code)}</span><span class="block text-xs text-muted-foreground">${escape_html(item.program.name)}</span>`);
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
                                    class: "tabular-nums",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(item.quota?.slots ?? "Not set")}`);
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
                                    class: "tabular-nums",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(item.commitments)}`);
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
                                    class: "tabular-nums",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(item.remaining ?? "—")}`);
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
                                      if (item.over_capacity > 0) {
                                        $$renderer8.push("<!--[0-->");
                                        Badge($$renderer8, {
                                          variant: "destructive",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(item.over_capacity)} over capacity`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      } else if (!item.quota) {
                                        $$renderer8.push("<!--[1-->");
                                        Badge($$renderer8, {
                                          variant: "secondary",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->No quota`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                        $$renderer8.push(`<span class="text-xs text-muted-foreground">Within capacity</span>`);
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
                                    children: ($$renderer8) => {
                                      Button($$renderer8, {
                                        variant: "ghost",
                                        size: "sm",
                                        disabled: form.processing || grantBusy || contextLoading,
                                        onclick: () => {
                                          selectedProgramId = String(item.program.id);
                                        },
                                        "aria-label": `Open quota context for ${item.program.code}`,
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(String(item.program.id) === String(row().program.id) ? "Selected" : "Open")}`);
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
            $$renderer4.push(`</div></section> <div class="flex gap-2 lg:hidden" aria-label="Quota workspace views">`);
            Button($$renderer4, {
              variant: mobileView === "quota" ? "secondary" : "ghost",
              class: "min-h-11 flex-1",
              "aria-pressed": mobileView === "quota",
              onclick: () => showView("quota"),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Quota`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              variant: mobileView === "discussion" ? "secondary" : "ghost",
              class: "min-h-11 flex-1",
              "aria-pressed": mobileView === "discussion",
              onclick: () => showView("discussion"),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Discussion`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              variant: mobileView === "changes" ? "secondary" : "ghost",
              class: "min-h-11 flex-1",
              "aria-pressed": mobileView === "changes",
              onclick: () => showView("changes"),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Changes`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]"><section${attr_class(`min-w-0 space-y-4 ${mobileView === "quota" ? "" : "hidden lg:block"}`)} aria-labelledby="selected-quota-heading"><div class="flex flex-wrap items-center justify-between gap-2"><h2 id="selected-quota-heading" class="text-base font-semibold">${escape_html(row().program.code)} · quota</h2> `);
            if (!row().can_manage) {
              $$renderer4.push("<!--[0-->");
              Badge($$renderer4, {
                variant: "secondary",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Read only`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <dl class="flex flex-wrap gap-x-6 gap-y-3 border-y border-border py-3 text-sm"><div><dt class="text-muted-foreground">Capacity</dt><dd class="font-semibold tabular-nums">${escape_html(row().quota?.slots ?? "Not set")}</dd></div> <div><dt class="text-muted-foreground">Committed</dt><dd class="font-semibold tabular-nums">${escape_html(row().commitments)}</dd></div> <div><dt class="text-muted-foreground">Remaining</dt><dd class="font-semibold tabular-nums">${escape_html(row().remaining ?? "—")}</dd></div> `);
            if (row().over_capacity > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div><dt class="text-destructive">Over capacity</dt><dd class="font-semibold tabular-nums text-destructive">${escape_html(row().over_capacity)}</dd></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></dl> `);
            if (row().over_capacity > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">Existing commitments exceed capacity by ${escape_html(row().over_capacity)}. Commitments are preserved.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (row().quota) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-xs text-muted-foreground">Updated by ${escape_html(row().quota.last_mutated_by || "Unrecorded staff")} · ${escape_html(formatTime(row().quota.updated_at))}</p> `);
              if (row().quota.last_notice) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="whitespace-pre-wrap break-words text-sm">${escape_html(row().quota.last_notice)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (row().can_manage) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<form class="grid gap-3 rounded-xl border border-border bg-card p-4"><div class="space-y-1.5">`);
              Label($$renderer4, {
                for: "quota-slots",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Slots`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Input($$renderer4, {
                id: "quota-slots",
                type: "number",
                min: "0",
                max: "100000",
                step: "1",
                required: true,
                disabled: form.processing || contextLoading,
                "aria-invalid": !!form.errors.slots,
                "aria-describedby": form.errors.slots ? "quota-slots-error" : void 0,
                class: "min-h-11",
                get value() {
                  return form.slots;
                },
                set value($$value) {
                  form.slots = $$value;
                  $$settled = false;
                }
              });
              $$renderer4.push(`<!----> `);
              if (form.errors.slots) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p id="quota-slots-error" role="alert" class="text-sm text-destructive">${escape_html(form.errors.slots)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
              Label($$renderer4, {
                for: "quota-notice",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(is_program_head ? "Note (optional)" : "Notice to Program Head (recommended)")}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> <textarea id="quota-notice" rows="3" maxlength="2000"${attr("disabled", form.processing || contextLoading, true)}${attr("aria-invalid", !!form.errors.notice)} class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring" placeholder="Explain the quota change.">`);
              const $$body = escape_html(form.notice);
              if ($$body) {
                $$renderer4.push(`${$$body}`);
              }
              $$renderer4.push(`</textarea> `);
              if (form.errors.notice) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(form.errors.notice)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> `);
              if (form.errors.program_id || form.errors.academic_cycle_id) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(form.errors.program_id || form.errors.academic_cycle_id)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> <p class="text-xs text-muted-foreground">This is the existing quota save path. Concurrent-version protection is not shipped; refresh current facts before changing capacity.</p> `);
              Button($$renderer4, {
                type: "submit",
                class: "min-h-11 w-fit",
                disabled: form.processing || contextLoading,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(form.processing ? "Saving…" : "Save quota")}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (form.recentlySuccessful) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p role="status" class="text-sm">Program quota saved.</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></form>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="text-sm text-muted-foreground">You can read this program's quota context. Discussion access does not authorize capacity changes.</p>`);
            }
            $$renderer4.push(`<!--]--> `);
            if (can_manage_registrar_grants) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<section class="space-y-3 border-t border-border pt-5" aria-labelledby="registrar-access-heading"><h3 id="registrar-access-heading" class="text-sm font-semibold">Registrar quota access</h3> <p class="text-xs text-muted-foreground">Read quota facts and history, and discuss quotas for ${escape_html(row().program.code)} · ${escape_html(cycle.staff_label || cycle.name)} only. No capacity edits, applicant records, benchmarks or advising access.</p> `);
              if (scopedGrants().length) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<ul class="divide-y divide-border text-sm"><!--[-->`);
                const each_array_3 = ensure_array_like(scopedGrants());
                for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                  let grant = each_array_3[$$index_3];
                  $$renderer4.push(`<li class="space-y-1 py-2"><div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">${escape_html(grant.recipient_name || "Registrar")}</span>`);
                  Badge($$renderer4, {
                    variant: "secondary",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(grant.status)} · v${escape_html(grant.version)}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push(`<!----></div> <p class="whitespace-pre-wrap break-words text-xs text-muted-foreground">${escape_html(grant.reason)}</p> `);
                  if (grant.actor_name) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<p class="text-xs text-muted-foreground">By ${escape_html(grant.actor_name)}</p>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--></li>`);
                }
                $$renderer4.push(`<!--]--></ul>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (grantDenied) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html("Grant management is no longer available in this context.")}</p> `);
                Button($$renderer4, {
                  variant: "outline",
                  onclick: refreshContext,
                  disabled: contextLoading,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Refresh access`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!---->`);
              } else if (!registrar_candidates.length) {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`<p class="text-sm text-muted-foreground">No active Registrar recipients are available in this institution.</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<form class="grid gap-3"><div class="space-y-1.5">`);
                Label($$renderer4, {
                  for: "quota-grant-recipient",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Registrar recipient`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!---->`);
                $$renderer4.select(
                  {
                    id: "quota-grant-recipient",
                    value: grantRecipient,
                    required: true,
                    disabled: contextLoading,
                    class: "flex min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                  },
                  ($$renderer5) => {
                    $$renderer5.option({ value: "" }, ($$renderer6) => {
                      $$renderer6.push(`Select a Registrar`);
                    });
                    $$renderer5.push(`<!--[-->`);
                    const each_array_4 = ensure_array_like(registrar_candidates);
                    for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                      let candidate = each_array_4[$$index_4];
                      $$renderer5.option({ value: String(candidate.id) }, ($$renderer6) => {
                        $$renderer6.push(`${escape_html(candidate.name)}`);
                      });
                    }
                    $$renderer5.push(`<!--]-->`);
                  }
                );
                $$renderer4.push(`</div> `);
                {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> <div class="space-y-1.5">`);
                Label($$renderer4, {
                  for: "quota-grant-reason",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Reason for ${escape_html(selectedGrant()?.status === "active" ? "revocation" : "grant")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----><textarea id="quota-grant-reason" rows="2" required="" maxlength="1000"${attr("disabled", contextLoading, true)} class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring">`);
                const $$body_1 = escape_html(grantReason);
                if ($$body_1) {
                  $$renderer4.push(`${$$body_1}`);
                }
                $$renderer4.push(`</textarea></div> `);
                {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                Button($$renderer4, {
                  type: "submit",
                  variant: selectedGrant()?.status === "active" ? "destructive" : "default",
                  class: "min-h-11 w-fit",
                  disabled: contextLoading || !grantRecipient || !grantReason.trim(),
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(selectedGrant()?.status === "active" ? "Revoke quota access" : "Grant quota read + discussion")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></form>`);
              }
              $$renderer4.push(`<!--]--></section>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section> <aside${attr_class(`min-w-0 space-y-4 ${mobileView === "quota" ? "hidden lg:block" : ""}`)} aria-label="Quota discussion and changes"><div class="hidden gap-2 lg:flex">`);
            Button($$renderer4, {
              variant: panelView === "discussion" ? "secondary" : "ghost",
              "aria-pressed": panelView === "discussion",
              onclick: () => {
                panelView = "discussion";
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Discussion`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
            Button($$renderer4, {
              variant: panelView === "changes" ? "secondary" : "ghost",
              "aria-pressed": panelView === "changes",
              onclick: () => {
                panelView = "changes";
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Changes`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div${attr_class(clsx(panelView === "discussion" ? "" : "hidden"))}><!---->`);
            {
              if (row().discussion !== null && row().discussion !== void 0) {
                $$renderer4.push("<!--[0-->");
                GovernanceDiscussionPanel($$renderer4, {
                  program: row().program,
                  cycle,
                  topic: "quota",
                  initialList: row().discussion
                });
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<p class="text-sm text-muted-foreground">Quota discussion is not available under your current access.</p>`);
              }
              $$renderer4.push(`<!--]-->`);
            }
            $$renderer4.push(`<!----></div> <section${attr_class(`space-y-3 ${panelView === "changes" ? "" : "hidden"}`)} aria-labelledby="quota-changes-heading"><h3 id="quota-changes-heading" class="text-sm font-semibold">Changes · ${escape_html(row().program.code)}</h3> <p class="text-xs text-muted-foreground">Showing the ${escape_html(row().quota?.changes?.length ?? 0)} supplied recent changes (up to 8). Older quota history is not available on this page.</p> `);
            if (row().quota?.changes?.length) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<ol class="divide-y divide-border"><!--[-->`);
              const each_array_5 = ensure_array_like(row().quota.changes);
              for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                let change = each_array_5[$$index_5];
                $$renderer4.push(`<li class="space-y-1 py-3 text-sm"><div class="flex flex-wrap items-baseline justify-between gap-2"><span class="font-medium tabular-nums">${escape_html(change.from_slots ?? "Not set")} → ${escape_html(change.to_slots)} slots</span><time${attr("datetime", change.created_at || void 0)} class="text-xs text-muted-foreground">${escape_html(formatTime(change.created_at))}</time></div><p class="text-xs text-muted-foreground">By ${escape_html(change.actor || "Unrecorded staff")} · change #${escape_html(change.id)}</p>`);
                if (change.notice) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="whitespace-pre-wrap break-words">${escape_html(change.notice)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></li>`);
              }
              $$renderer4.push(`<!--]--></ol>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="text-sm text-muted-foreground">No quota changes have been supplied for this program and academic year.</p>`);
            }
            $$renderer4.push(`<!--]--></section></aside></div>`);
          }
          $$renderer4.push(`<!--]--></div>`);
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
  Quotas as default
};
