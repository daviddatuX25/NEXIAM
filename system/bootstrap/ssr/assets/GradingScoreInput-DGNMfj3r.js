import { n as newIdempotencyKey, h as head, e as escape_html, a as ensure_array_like, b as attr, s as stringify, B as Button, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { M as ModeBanner } from "./ModeBanner-CuWMSWxe.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { U as User } from "./user-zHTdFo0I.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
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
function GradingScoreInput($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      degraded = false,
      session_id = null,
      session_status = "open",
      finalized = false,
      is_profile_bound = false,
      attempt = {},
      exam = {},
      domains = [],
      binding_state = null,
      administration_state = null
    } = $$props;
    const page = usePage();
    const saveError = derived(() => page.props.errors?.scores ?? "");
    const idempotencyKey = newIdempotencyKey();
    let scores = Object.fromEntries(domains.map((metric) => [
      String(metric.metric_id ?? metric.id),
      metric.raw_value ?? metric.score ?? ""
    ]));
    let saving = false;
    let clearing = false;
    let clearDialog = false;
    function scoreKey(metric) {
      return String(metric.metric_id ?? metric.id);
    }
    const hasScores = derived(() => domains.some((metric) => !metric.is_derived && scores[scoreKey(metric)] !== "" && scores[scoreKey(metric)] !== null));
    function convertedFor(metric) {
      const raw = Number(scores[scoreKey(metric)]);
      if (scores[scoreKey(metric)] === "" || scores[scoreKey(metric)] === null || isNaN(raw)) return null;
      if (metric.scoring_method !== "conversion_table") return null;
      const row = metric.conversion_rows?.find((r) => raw >= Number(r.min_score) && raw <= Number(r.max_score));
      return row ? row.output_value : null;
    }
    const breadcrumbs = [
      { label: "Scoring", href: "/grading" },
      {
        label: `Session ${exam.code ?? ""}`,
        href: `/grading/sessions/${session_id}`
      },
      { label: attempt.reference_code ?? "Applicant" }
    ];
    function saveScores() {
      if (finalized || saving) return;
      const payload = domains.map((metric) => {
        const value = scores[scoreKey(metric)];
        if (metric.is_derived || metric.read_only || value === "" || value === null || value === void 0) return null;
        if (is_profile_bound) {
          return {
            metric_id: metric.metric_id ?? metric.id,
            metric_code: metric.metric_code ?? metric.code,
            score: Number(value)
          };
        }
        return {
          aptitude_area_id: metric.aptitude_area_id ?? metric.id,
          score: Number(value)
        };
      }).filter((entry) => entry !== null);
      if (payload.length === 0) return;
      saving = true;
      router.put(`/grading/sessions/${session_id}/attempts/${attempt.id}/scores`, { scores: payload, idempotency_key: idempotencyKey }, {
        preserveScroll: true,
        onFinish: () => {
          saving = false;
        }
      });
    }
    function clearScores() {
      if (finalized || clearing) return;
      clearing = true;
      router.delete(`/grading/sessions/${session_id}/attempts/${attempt.id}/scores`, {
        onFinish: () => {
          clearing = false;
          clearDialog = false;
        }
      });
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("fdj778", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(attempt.reference_code ?? "Applicant")} — Scores</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 w-full max-w-7xl space-y-6 p-4 sm:p-6">`);
          if (degraded) {
            $$renderer4.push("<!--[0-->");
            ModeBanner($$renderer4, {
              variant: "warning",
              title: "Manual grading mode",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Inference provider unavailable; manual entry remains authoritative.`);
              }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (finalized) {
            $$renderer4.push("<!--[0-->");
            ModeBanner($$renderer4, {
              variant: "info",
              title: "Finalized",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->This grading session is finalized — scores are locked and can no longer be edited.`);
              }
            });
          } else if (binding_state === "awaiting_norm_conversion" || administration_state === "awaiting_norm_conversion") {
            $$renderer4.push("<!--[1-->");
            ModeBanner($$renderer4, {
              variant: "info",
              title: "Awaiting norm conversion",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->All required raw results have been accepted. Raw results are preserved and awaiting approved norm conversion.`);
              }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
            ModeBanner($$renderer4, {
              variant: "warning",
              title: "Manual mode",
              children: ($$renderer5) => {
                if (is_profile_bound) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`Enter raw values for each required measure. Derived metrics are read-only and calculated from their components.`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`Enter the raw score for each aptitude area. The converted value previews live from the area's confidential conversion table.`);
                }
                $$renderer5.push(`<!--]-->`);
              }
            });
          }
          $$renderer4.push(`<!--]--> <div class="rounded-xl border border-border bg-card p-6"><div class="flex items-center justify-between gap-2"><div class="flex items-center gap-2">`);
          User($$renderer4, { class: "size-5 text-muted-foreground" });
          $$renderer4.push(`<!----> <h1 class="text-xl font-semibold tracking-tight">${escape_html(attempt.name)}</h1></div> `);
          if (binding_state === "awaiting_norm_conversion" || administration_state === "awaiting_norm_conversion") {
            $$renderer4.push("<!--[0-->");
            Badge($$renderer4, {
              variant: "outline",
              class: "gap-1 border-amber-500/50 text-amber-700 bg-amber-50/50",
              children: ($$renderer5) => {
                Clock($$renderer5, { class: "size-3" });
                $$renderer5.push(`<!----> Awaiting norm conversion`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <p class="text-sm text-muted-foreground">Reference: ${escape_html(attempt.reference_code)} · ${escape_html(exam.code ?? "—")} · ${escape_html(exam.starts_at ?? "—")} · 
        ${escape_html(exam.room_name ?? "—")}</p> <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><!--[-->`);
          const each_array = ensure_array_like(domains);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let metric = each_array[$$index];
            $$renderer4.push(`<div class="rounded-lg border border-border p-4"><label class="text-sm font-medium leading-none"${attr("for", `score-${stringify(scoreKey(metric))}`)}>${escape_html(metric.name)} (${escape_html(metric.metric_code ?? metric.code)})</label> `);
            if (metric.is_derived) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="mt-2">`);
              Badge($$renderer4, {
                variant: "outline",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Derived · read-only`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="mt-2 flex items-center gap-3">`);
            Input($$renderer4, {
              id: `score-${stringify(scoreKey(metric))}`,
              type: "number",
              min: metric.min_raw_value ?? 0,
              max: metric.max_raw_value ?? 100,
              step: metric.precision_scale > 0 ? 1 / Math.pow(10, metric.precision_scale) : 1,
              value: scores[scoreKey(metric)],
              disabled: finalized || metric.is_derived || metric.read_only,
              placeholder: "0.00",
              class: "w-24",
              oninput: (e) => {
                scores[scoreKey(metric)] = e.currentTarget.value;
              }
            });
            $$renderer4.push(`<!----> <span class="text-sm text-muted-foreground">/ ${escape_html(metric.max_raw_value ?? 100)} ${escape_html(metric.unit ?? "points")}</span></div> `);
            if (metric.scoring_method === "conversion_table") {
              $$renderer4.push("<!--[0-->");
              if (finalized) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="mt-2 text-sm font-medium text-emerald-700"${attr("data-testid", `converted-${stringify(metric.id)}`)}>Converted: ${escape_html(metric.converted_score ?? "—")}</p>`);
              } else if (convertedFor(metric)) {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`<p class="mt-2 text-sm font-medium text-emerald-700"${attr("data-testid", `converted-preview-${stringify(metric.id)}`)}>Converted: ${escape_html(convertedFor(metric))}</p>`);
              } else if (scores[scoreKey(metric)] !== "" && scores[scoreKey(metric)] !== null) {
                $$renderer4.push("<!--[2-->");
                $$renderer4.push(`<p class="mt-2 text-sm text-destructive"${attr("data-testid", `converted-missing-${stringify(metric.id)}`)}>Outside conversion table</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (saveError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="mt-4 text-sm text-destructive" data-testid="score-error">${escape_html(saveError())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="mt-6 flex flex-wrap gap-3">`);
          if (!finalized) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              class: "min-h-10 gap-2",
              disabled: saving || clearing,
              onclick: saveScores,
              children: ($$renderer5) => {
                Save($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> ${escape_html(saving ? "Saving…" : "Save scores")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (hasScores()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                variant: "destructive",
                class: "min-h-10 gap-2",
                disabled: saving || clearing,
                onclick: () => clearDialog = true,
                children: ($$renderer5) => {
                  Trash_2($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> ${escape_html(clearing ? "Clearing…" : "Clear scores")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></div></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return clearDialog;
              },
              set open($$value) {
                clearDialog = $$value;
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
                                  $$renderer8.push(`<!---->Clear all scores`);
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
                                  $$renderer8.push(`<!---->This removes every aptitude area score for ${escape_html(attempt.name)} (${escape_html(attempt.reference_code)}) in
          this grading session. Scores can be re-entered later while the session is open.`);
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
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => clearDialog = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "destructive",
                              disabled: clearing,
                              onclick: clearScores,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(clearing ? "Clearing…" : "Clear scores")}`);
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
          if (finalized) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex items-center gap-2 text-sm text-muted-foreground">`);
            Lock($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!----> Scores are locked for this finalized grading session.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]-->`);
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
  GradingScoreInput as default
};
