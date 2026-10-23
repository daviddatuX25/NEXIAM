import { a as ensure_array_like, e as escape_html, b as attr, B as Button, d as derived, v as error, q as success } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./input-BUZAZKIK.js";
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
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      booklet = {},
      pairs = [],
      areas = [],
      capabilities = {},
      errors = {}
    } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials", href: "/setup/exam-materials" },
      { label: booklet.name ?? "Booklet" }
    ];
    const versions = derived(() => booklet.versions ?? []);
    const newestActive = derived(() => versions().find((v) => v.status === "active") ?? null);
    const newestDraft = derived(() => versions().find((v) => v.status === "draft") ?? null);
    function statusVariant(status) {
      if (status === "active") return "default";
      if (status === "draft") return "secondary";
      return "outline";
    }
    let confirming = null;
    let retiring = false;
    function cascadePairs(version) {
      if (Array.isArray(version.active_pair_ids)) {
        return version.active_pair_ids.map((id) => pairs.find((pair) => pair.id === id)).filter(Boolean);
      }
      if (pairs.some((pair) => pair.booklet_version_id != null)) {
        return pairs.filter((pair) => pair.booklet_version_id === version.id);
      }
      return [];
    }
    function requestRetire(kind, id, label, cascade = []) {
      confirming = { kind, id, label, pairs: cascade };
    }
    function confirmRetire() {
      if (!confirming || retiring) return;
      retiring = true;
      router.post("/setup/omr/retire", { kind: confirming.kind, id: confirming.id }, {
        preserveScroll: true,
        onSuccess: () => {
          success(`${confirming.label} retired.`);
          confirming = null;
        },
        onError: (errs) => {
          const first = errs.omr_retire ?? Object.values(errs)[0];
          if (first) error(first);
        },
        onFinish: () => {
          retiring = false;
        }
      });
    }
    let copying = false;
    function copyActiveVersion() {
      if (!newestActive() || copying) return;
      copying = true;
      router.post(`/setup/omr/booklets/${booklet.id}/copy`, { version_id: newestActive().id }, {
        preserveScroll: true,
        onSuccess: () => success(`Draft copied from v${newestActive().version}.`),
        onError: (errs) => {
          error(errs.omr_booklet ?? Object.values(errs)[0] ?? "The draft could not be copied.");
        },
        onFinish: () => {
          copying = false;
        }
      });
    }
    function areaGroups(questions) {
      const groups = [];
      const index = /* @__PURE__ */ new Map();
      for (const question of questions ?? []) {
        if (!index.has(question.area_code)) {
          index.set(question.area_code, groups.length);
          groups.push({ area_code: question.area_code, questions: [] });
        }
        groups[index.get(question.area_code)].questions.push(question);
      }
      return groups;
    }
    function optionDistribution(questions) {
      const counts = { A: 0, B: 0, C: 0, D: 0 };
      for (const question of questions ?? []) {
        if (counts[question.correct_option_code] !== void 0) counts[question.correct_option_code] += 1;
      }
      return ["A", "B", "C", "D"].filter((letter) => counts[letter] > 0).map((letter) => `${letter} ${counts[letter]}`).join(" · ");
    }
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        {
          let actions = function($$renderer4) {
            if (newestActive()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                onclick: copyActiveVersion,
                disabled: copying,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(copying ? "Copying…" : "New draft from this version")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (newestDraft()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                asChild: true,
                variant: "outline",
                children: ($$renderer5) => {
                  Link($$renderer5, {
                    href: `/setup/omr/booklets/create?draft=${newestDraft().id}`,
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Edit draft`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            Button($$renderer4, {
              asChild: true,
              variant: "outline",
              children: ($$renderer5) => {
                Link($$renderer5, {
                  href: "/setup/omr/booklets/create",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Author new booklet`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
          };
          PageHeaderBar($$renderer3, {
            title: booklet.name ?? "Booklet",
            description: booklet.code ?? "",
            actions
          });
        }
        $$renderer3.push(`<!----> <section class="space-y-6 p-6"><div><h2 class="mb-3 text-lg font-semibold">Versions</h2> `);
        if (versions().length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground text-sm">No versions registered for this booklet yet.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="space-y-4"><!--[-->`);
          const each_array = ensure_array_like(versions());
          for (let $$index_3 = 0, $$length = each_array.length; $$index_3 < $$length; $$index_3++) {
            let version = each_array[$$index_3];
            $$renderer3.push(`<div class="rounded-lg border p-4"><div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2"><span class="font-mono font-semibold">v${escape_html(version.version)}</span> `);
            Badge($$renderer3, {
              variant: statusVariant(version.status),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(version.status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <div class="flex flex-wrap items-center gap-2"><span class="text-muted-foreground font-mono text-xs"${attr("title", version.answer_key_hash ?? "")}>${escape_html((version.answer_key_hash ?? "").slice(0, 12) || "—")}</span> `);
            if (version.status !== "retired") {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                variant: "outline",
                size: "sm",
                onclick: () => requestRetire("booklet_version", version.id, `booklet version v${version.version}`, cascadePairs(version)),
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Retire`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div></div> `);
            if (confirming?.kind === "booklet_version" && confirming.id === version.id) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-destructive/40 bg-destructive/5 mt-3 rounded-md border p-3 text-sm"><p class="font-medium">Retire v${escape_html(version.version)}?</p> `);
              if (confirming.pairs.length > 0) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="text-muted-foreground mt-1">This also retires the following
                                            pair(s):</p> <ul class="mt-2 space-y-1"><!--[-->`);
                const each_array_1 = ensure_array_like(confirming.pairs);
                for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                  let pair = each_array_1[$$index];
                  $$renderer3.push(`<li class="text-muted-foreground">• ${escape_html(pair.label)}</li>`);
                }
                $$renderer3.push(`<!--]--></ul>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<p class="text-muted-foreground mt-1">No pairs are attached to this
                                            version.</p>`);
              }
              $$renderer3.push(`<!--]--> <div class="mt-3 flex gap-2">`);
              Button($$renderer3, {
                size: "sm",
                variant: "destructive",
                onclick: confirmRetire,
                disabled: retiring,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(retiring ? "Retiring…" : `Retire v${version.version}`)}`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----> `);
              Button($$renderer3, {
                size: "sm",
                variant: "outline",
                onclick: () => confirming = null,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></div></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <div class="mt-3"><div class="mb-1 flex items-baseline gap-3"><h3 class="text-sm font-semibold">Key preview</h3> <span class="text-muted-foreground text-xs">${escape_html((version.questions ?? []).length)} question${escape_html((version.questions ?? []).length === 1 ? "" : "s")}</span></div> `);
            if ((version.questions ?? []).length === 0) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-muted-foreground text-sm">No questions in this version.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<div class="space-y-3"><!--[-->`);
              const each_array_2 = ensure_array_like(areaGroups(version.questions));
              for (let $$index_2 = 0, $$length2 = each_array_2.length; $$index_2 < $$length2; $$index_2++) {
                let group = each_array_2[$$index_2];
                $$renderer3.push(`<div><p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">${escape_html(group.area_code)} (${escape_html(group.questions.length)} question${escape_html(group.questions.length === 1 ? "" : "s")})</p> <div class="mt-1.5 flex flex-wrap gap-1.5"><!--[-->`);
                const each_array_3 = ensure_array_like(group.questions);
                for (let $$index_1 = 0, $$length3 = each_array_3.length; $$index_1 < $$length3; $$index_1++) {
                  let question = each_array_3[$$index_1];
                  $$renderer3.push(`<span class="rounded border px-2 py-0.5 font-mono text-xs">P${escape_html(question.position)} <strong class="text-primary">${escape_html(question.correct_option_code)}</strong></span>`);
                }
                $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground mt-1 font-mono text-xs">${escape_html(optionDistribution(group.questions))}</p></div>`);
              }
              $$renderer3.push(`<!--]--></div>`);
            }
            $$renderer3.push(`<!--]--></div></div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--></div> <div><h2 class="mb-3 text-lg font-semibold">Pairs</h2> `);
        if (pairs.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground text-sm">No template pairs registered for this booklet.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="space-y-2"><!--[-->`);
          const each_array_4 = ensure_array_like(pairs);
          for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
            let pair = each_array_4[$$index_4];
            $$renderer3.push(`<div class="rounded-lg border p-3"><div class="flex items-center justify-between gap-2"><div class="flex min-w-0 items-center gap-2"><span class="truncate text-sm">${escape_html(pair.label)}</span> `);
            Badge($$renderer3, {
              variant: statusVariant(pair.status),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(pair.status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> `);
            if (pair.status === "active") {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                variant: "outline",
                size: "sm",
                onclick: () => requestRetire("pair", pair.id, `pair ${pair.label}`),
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Retire`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> `);
            if (confirming?.kind === "pair" && confirming.id === pair.id) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-destructive/40 bg-destructive/5 mt-3 rounded-md border p-3 text-sm"><p class="font-medium">Retire this pair?</p> <p class="text-muted-foreground mt-1">${escape_html(pair.label)}</p> <div class="mt-3 flex gap-2">`);
              Button($$renderer3, {
                size: "sm",
                variant: "destructive",
                onclick: confirmRetire,
                disabled: retiring,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(retiring ? "Retiring…" : "Retire")}`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----> `);
              Button($$renderer3, {
                size: "sm",
                variant: "outline",
                onclick: () => confirming = null,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></div></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--></div></section>`);
      }
    });
  });
}
export {
  Show as default
};
