import { D as props_id, b as attr, e as escape_html, a as ensure_array_like, B as Button, d as derived, c as attr_class, A as clsx, m as csrfHeaders } from "../app.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./Icon-Dal7PqKK.js";
function PracticePreparationPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      purpose = "demo",
      scenario = "application-review",
      active = true,
      can_control = false,
      initial = null,
      index_url = "/demo/preparation",
      onbusychange = () => {
      }
    } = $$props;
    const presets = [
      ["72h-reminder", "Starts in 72 hours"],
      ["24h-reminder", "Starts in 24 hours"],
      ["2h-reminder", "Starts in 2 hours"],
      ["1h-reminder", "Starts in 1 hour"],
      ["soon", "Starts in 45 minutes"],
      ["door-open", "Door check-in window"],
      ["live", "Live exam"],
      ["ended", "Ended exam"]
    ];
    const windows = ["72h", "24h", "2h", "1h"];
    let selectedScenario = scenario;
    let selectedSetId = "";
    let bookletVersionId = "";
    let sessionId = "";
    let preset = "1h-reminder";
    let windowKey = "1h";
    let preparation = null;
    let pending = null;
    let error = "";
    let message = "";
    let result = null;
    let preview = null;
    let confirmationOpen = false;
    let reviewDialog = null;
    let confirmationError = "";
    let expired = false;
    let retryConfirmation = false;
    let requestEpoch = 0;
    let controller = null;
    const selectedSet = derived(() => preparation?.selected_set ?? null);
    const current = derived(() => preparation?.purpose === purpose && preparation?.scenario === selectedScenario && (preparation?.selected_set?.id ?? "") === selectedSetId && String(preparation?.materials?.selected_booklet_version_id ?? "") === bookletVersionId);
    const busy = derived(() => pending !== null);
    function invalidate() {
      requestEpoch += 1;
      controller?.abort();
      controller = null;
      pending = null;
    }
    function relativeUrl(value) {
      if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//") || value.includes("\\")) return null;
      if (typeof window === "undefined") return value;
      const url = new URL(value, window.location.origin);
      return url.origin === window.location.origin ? `${url.pathname}${url.search}${url.hash}` : null;
    }
    function begin(kind) {
      invalidate();
      controller = new AbortController();
      pending = kind;
      return { epoch: requestEpoch, signal: controller.signal };
    }
    async function request(url, signal, body) {
      const safeUrl = relativeUrl(url);
      if (!safeUrl) throw new Error("The server did not return a current-origin preparation link. Refresh the preparation details.");
      const response = await fetch(safeUrl, {
        method: body === void 0 ? "GET" : "POST",
        credentials: "same-origin",
        redirect: "error",
        signal,
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "ngrok-skip-browser-warning": "1",
          ...body === void 0 ? {} : { "Content-Type": "application/json", ...csrfHeaders() }
        },
        ...body === void 0 ? {} : { body: JSON.stringify(body) }
      });
      if (!(response.headers.get("content-type") ?? "").includes("application/json")) {
        throw new Error("Preparation returned a non-JSON response. Your previous details are retained; sign in or refresh before retrying.");
      }
      const data = await response.json();
      if (!response.ok) {
        const fallback = response.status === 409 ? "Preparation changed or this review expired. Refresh details and explicitly review a new operation." : response.status === 403 || response.status === 404 ? "You no longer have access to this preparation." : response.status === 419 || response.status === 401 ? "Your session expired. Sign in or refresh before retrying." : "This operation is blocked. Review the prerequisites before retrying.";
        const failure = new Error(typeof data.message === "string" ? data.message : fallback);
        failure.status = response.status;
        throw failure;
      }
      return data;
    }
    function acceptPreparation(data) {
      if (data?.purpose !== purpose || data?.scenario !== selectedScenario || !Array.isArray(data.scenarios) || !Array.isArray(data.actions)) throw new Error("Preparation details do not match the selected purpose and scenario. Refresh to try again.");
      if (data.selected_set && (data.selected_set.purpose !== purpose || data.selected_set.scenario !== selectedScenario)) throw new Error("The returned practice set does not match this scenario.");
      preparation = data;
      selectedSetId = data.selected_set?.id ?? "";
      bookletVersionId = String(data.materials?.selected_booklet_version_id ?? "");
      sessionId = data.selected_set?.sessions?.some((session) => String(session.id) === sessionId) ? sessionId : String(data.selected_set?.sessions?.[0]?.id ?? "");
    }
    async function loadPreparation() {
      const { epoch, signal } = begin("load");
      preview = null;
      confirmationOpen = false;
      error = "";
      message = "";
      result = null;
      try {
        const safeIndex = relativeUrl(preparation?.endpoints?.index ?? index_url);
        if (!safeIndex) throw new Error("The preparation index link is not on the current origin.");
        const url = new URL(safeIndex, window.location.origin);
        url.search = new URLSearchParams({
          purpose,
          scenario: selectedScenario,
          ...selectedSetId ? { set_id: selectedSetId } : {},
          ...bookletVersionId ? { booklet_version_id: bookletVersionId } : {}
        }).toString();
        const data = await request(`${url.pathname}${url.search}`, signal);
        if (epoch === requestEpoch) acceptPreparation(data);
      } catch (failure) {
        if (epoch === requestEpoch && failure.name !== "AbortError") error = failure instanceof TypeError ? "Could not reach preparation. Previous details are retained; check your connection and refresh details." : failure.message;
      } finally {
        if (epoch === requestEpoch) pending = null;
      }
    }
    function actionFor(key) {
      return preparation?.actions?.find((action) => action.key === key);
    }
    function permitted(key) {
      return current() && !busy() && actionFor(key)?.enabled === true;
    }
    async function review(key) {
      if (!permitted(key) || ["time", "reminder"].includes(key) && !sessionId) return;
      const { epoch, signal } = begin("preview");
      error = "";
      message = "";
      result = null;
      confirmationError = "";
      retryConfirmation = false;
      preview = null;
      const body = {
        purpose,
        scenario: selectedScenario,
        action: key,
        ...selectedSetId ? { set_id: selectedSetId } : {},
        ...["prepare", "replace"].includes(key) && bookletVersionId ? { booklet_version_id: Number(bookletVersionId) } : {},
        ...key === "time" ? { session_id: sessionId, preset } : {},
        ...key === "reminder" ? { session_id: sessionId, window: windowKey } : {}
      };
      try {
        const data = await request(preparation.endpoints.preview, signal, body);
        if (epoch !== requestEpoch) return;
        if (!data.operation?.id || data.operation.action !== key || !relativeUrl(data.operation.confirm_url)) throw new Error("The server did not return an exact confirmation operation. No change was confirmed.");
        preview = data;
        confirmationOpen = true;
      } catch (failure) {
        if (epoch === requestEpoch && failure.name !== "AbortError") error = failure instanceof TypeError ? "Could not load the impact review. No confirmation was sent; check your connection and try reviewing again." : failure.message;
      } finally {
        if (epoch === requestEpoch) pending = null;
      }
    }
    function cancelReview() {
      if (pending === "confirm") return;
      invalidate();
      preview = null;
      confirmationOpen = false;
      confirmationError = "";
      retryConfirmation = false;
    }
    async function confirmOperation() {
      if (!preview?.can_confirm || busy() || expired || !can_control || !active) return;
      const operation = preview.operation;
      const { epoch, signal } = begin("confirm");
      confirmationError = "";
      try {
        const data = await request(operation.confirm_url, signal, {});
        if (epoch !== requestEpoch) return;
        if (!data.preparation) throw new Error("The confirmation response has no resulting preparation details. Retry this same operation to recover its recorded outcome.");
        acceptPreparation(data.preparation);
        message = String(data.message ?? "The operation returned its recorded outcome.");
        result = data.result;
        preview = null;
        confirmationOpen = false;
        retryConfirmation = false;
      } catch (failure) {
        if (epoch !== requestEpoch || failure.name === "AbortError") return;
        confirmationError = failure instanceof TypeError ? "The confirmation outcome is unknown. Check your connection and retry this same operation to recover its recorded outcome." : failure.message;
        retryConfirmation = ![401, 403, 404, 409, 419, 422].includes(failure.status);
        if (!retryConfirmation) preview = { ...preview, can_confirm: false };
      } finally {
        if (epoch === requestEpoch) pending = null;
      }
    }
    function timestamp(value) {
      if (!value) return "Not set";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }
    function displayValue(value) {
      if (value === null || value === void 0) return "Not reported";
      return typeof value === "object" ? String(value.label ?? value.name ?? value.title ?? JSON.stringify(value)) : String(value);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (can_control && active) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="practice-panel min-w-0 text-slate-100 svelte-1ag3f5j" aria-label="Practice preparation"${attr("aria-busy", busy())}><p class="max-w-prose text-sm leading-6 text-slate-300">Prepare fictional cases for ${escape_html(purpose === "research" ? "researcher-led practice" : "a panel rehearsal")}. Institutional settings and existing records stay protected. Physical outputs and study approval require their own evidence.</p> <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="min-w-0 text-sm font-semibold"${attr("for", `${uid}-scenario`)}>Scenario `);
        $$renderer3.select(
          {
            id: `${uid}-scenario`,
            value: selectedScenario,
            onchange: () => {
              selectedSetId = "";
              bookletVersionId = "";
            },
            disabled: busy() || !preparation,
            class: "mt-2 w-full min-w-0"
          },
          ($$renderer4) => {
            $$renderer4.push(`<!--[-->`);
            const each_array = ensure_array_like(preparation?.scenarios ?? [{ key: selectedScenario, label: selectedScenario }]);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let item = each_array[$$index];
              $$renderer4.option({ value: item.key }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(item.label)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</label> <label class="min-w-0 text-sm font-semibold"${attr("for", `${uid}-set`)}>Practice set `);
        $$renderer3.select(
          {
            id: `${uid}-set`,
            value: selectedSetId,
            disabled: busy() || !preparation,
            class: "mt-2 w-full min-w-0"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`New set`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array_1 = ensure_array_like((preparation?.sets ?? []).filter((set) => set.purpose === purpose && set.scenario === selectedScenario));
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let set = each_array_1[$$index_1];
              $$renderer4.option({ value: set.id }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(set.label)} · ${escape_html(set.status)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</label></div> `);
        if (preparation?.materials?.booklets?.length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="mt-4 max-w-xl"><label${attr("for", `${uid}-booklet`)} class="text-sm font-semibold">Exam material binding `);
          $$renderer3.select(
            {
              id: `${uid}-booklet`,
              value: bookletVersionId,
              disabled: busy(),
              class: "mt-2 w-full min-w-0"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "" }, ($$renderer5) => {
                $$renderer5.push(`Choose an eligible sheet and booklet binding`);
              });
              $$renderer4.push(`<!--[-->`);
              const each_array_2 = ensure_array_like(preparation.materials.booklets);
              for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                let item = each_array_2[$$index_2];
                $$renderer4.option({ value: String(item.id) }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(item.label)}`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(`</label> <p class="mt-2 text-sm leading-6 text-slate-400">Used only for a fresh set or replacement. Existing set material bindings and institutional assets stay unchanged.</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="mt-3 flex flex-wrap items-center gap-3">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
          disabled: busy(),
          onclick: loadPreparation,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Refresh details`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        if (pending) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="status" class="text-sm text-slate-300">${escape_html(pending === "load" ? "Loading preparation details…" : pending === "preview" ? "Reviewing exact impact…" : "Confirming this operation…")}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (pending === "preview") {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            onclick: cancelReview,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel review`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (error) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="alert" class="mt-4 break-words rounded-lg border border-rose-400/30 bg-rose-400/5 p-3 text-sm leading-6 text-rose-200">${escape_html(error)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (message) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div role="status" class="mt-4 rounded-lg border border-blue-400/30 bg-blue-400/5 p-3 text-sm leading-6 text-blue-100"><p>${escape_html(message)}</p><p class="mt-1">${escape_html(selectedSet() ? `${selectedSet().label} · ${selectedSet().status} · revision ${selectedSet().revision}` : "No practice set selected.")}</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (result) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<details class="mt-3"><summary>Recorded operation result</summary><dl class="mt-3 space-y-2 text-sm"><!--[-->`);
          const each_array_3 = ensure_array_like(Object.entries(result));
          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
            let [key, value] = each_array_3[$$index_3];
            $$renderer3.push(`<div class="min-w-0"><dt class="font-semibold">${escape_html(key.replaceAll("_", " "))}</dt><dd class="break-words text-slate-300">${escape_html(displayValue(value))}</dd></div>`);
          }
          $$renderer3.push(`<!--]--></dl></details>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (preparation) {
          $$renderer3.push("<!--[0-->");
          if (!current()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p role="status" class="mt-4 text-sm text-amber-200">Previous details are retained below. They do not match the current selection; actions are disabled until details refresh.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <p class="mt-5 text-sm text-slate-400">${escape_html(displayValue(preparation.context?.institution))} · ${escape_html(displayValue(preparation.context?.cycle))}</p> <h3 class="mt-3 break-words text-lg font-semibold text-white">${escape_html(selectedSet()?.label ?? "No practice set selected")}</h3> `);
          if (selectedSet()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="mt-1 text-sm text-slate-300">${escape_html(selectedSet().purpose)} · ${escape_html(selectedSet().status)} · revision ${escape_html(selectedSet().revision)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<p class="mt-1 text-sm text-slate-300">Review preparation to create a fresh set. Preview alone changes no cases or sessions.</p>`);
          }
          $$renderer3.push(`<!--]--> <section class="mt-5 border-t border-slate-800 pt-4" aria-label="Prerequisites"><h3 class="text-sm font-semibold">Prerequisites</h3> <ul class="mt-2 divide-y divide-slate-800">`);
          const each_array_4 = ensure_array_like(preparation.prerequisites ?? []);
          if (each_array_4.length !== 0) {
            $$renderer3.push("<!--[-->");
            for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
              let item = each_array_4[$$index_4];
              $$renderer3.push(`<li class="py-3"><p class="text-sm font-medium">${escape_html(item.label)} <span${attr_class(clsx(item.status === "available" ? "text-blue-200" : "text-amber-200"))}>· ${escape_html(item.status)}</span></p><p class="mt-1 break-words text-sm leading-6 text-slate-400">${escape_html(item.reason)}</p></li>`);
            }
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push(`<li class="py-3 text-sm text-amber-200">No prerequisite evidence was returned.</li>`);
          }
          $$renderer3.push(`<!--]--></ul></section> <details class="mt-3"><summary>Preserved configuration and records</summary><ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-300"><!--[-->`);
          const each_array_5 = ensure_array_like(preparation.preserved ?? []);
          for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
            let item = each_array_5[$$index_5];
            $$renderer3.push(`<li>${escape_html(item)}</li>`);
          }
          $$renderer3.push(`<!--]--></ul></details> <section class="mt-5 border-y border-slate-800 py-4" aria-label="Preparation actions"><div class="flex flex-wrap gap-2"><!--[-->`);
          const each_array_6 = ensure_array_like((preparation.actions ?? []).filter((action) => ["prepare", "replace", "retire"].includes(action.key)));
          for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
            let action = each_array_6[$$index_6];
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
              disabled: !permitted(action.key),
              onclick: () => review(action.key),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(action.label)}`);
              },
              $$slots: { default: true }
            });
          }
          $$renderer3.push(`<!--]--></div> <!--[-->`);
          const each_array_7 = ensure_array_like((preparation.actions ?? []).filter((action) => ["prepare", "replace", "retire"].includes(action.key) && action.reason));
          for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
            let action = each_array_7[$$index_7];
            $$renderer3.push(`<p class="mt-2 text-sm leading-6 text-slate-400">${escape_html(action.label)}: ${escape_html(action.reason)}</p>`);
          }
          $$renderer3.push(`<!--]--></section> `);
          if (selectedSet()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<details class="mt-4"><summary>Assigned accounts and access checks</summary> <p class="mt-3 text-sm leading-6 text-slate-400">Sign in normally using separate browser profiles. These links do not switch identity or grant authority. No staff password is inferred.</p> <ul class="mt-2 divide-y divide-slate-800">`);
            const each_array_8 = ensure_array_like(selectedSet().actors ?? []);
            if (each_array_8.length !== 0) {
              $$renderer3.push("<!--[-->");
              for (let index = 0, $$length = each_array_8.length; index < $$length; index++) {
                let actor = each_array_8[index];
                $$renderer3.push(`<li class="py-4 text-sm"><h4 class="font-semibold">${escape_html(actor.name)} · ${escape_html(actor.role)}</h4><p class="mt-1 break-all text-slate-300">${escape_html(actor.email)}</p>`);
                if (actor.access_key) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="mt-2 break-all text-slate-300">Applicant access key: <code>${escape_html(actor.access_key)}</code></p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]-->`);
                if (actor.birthdate) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="mt-1 text-slate-300">Birthdate: ${escape_html(actor.birthdate)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]-->`);
                if (relativeUrl(actor.login_url)) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<a class="mt-2 inline-flex min-h-11 items-center"${attr("href", relativeUrl(actor.login_url))} target="_blank" rel="noopener noreferrer">Open sign in (new tab)</a>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--><ul class="mt-2 space-y-2"><!--[-->`);
                const each_array_9 = ensure_array_like(actor.checks ?? []);
                for (let index2 = 0, $$length2 = each_array_9.length; index2 < $$length2; index2++) {
                  let check = each_array_9[index2];
                  $$renderer3.push(`<li class="leading-6"><span${attr_class(clsx(check.allowed ? "text-blue-200" : "text-amber-200"))}>${escape_html(check.label)}: ${escape_html(check.allowed ? "Allowed" : "Not allowed")}</span>`);
                  if (check.reason) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<p class="text-slate-400">${escape_html(check.reason)}</p>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--></li>`);
                }
                $$renderer3.push(`<!--]--></ul></li>`);
              }
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push(`<li class="py-3 text-sm text-slate-400">No assigned account returned.</li>`);
            }
            $$renderer3.push(`<!--]--></ul></details> `);
            if (selectedSet().assignments?.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<details class="mt-3"><summary>Assigned task cards and accounts</summary> <ul class="mt-2 divide-y divide-slate-800"><!--[-->`);
              const each_array_10 = ensure_array_like(selectedSet().assignments);
              for (let index = 0, $$length = each_array_10.length; index < $$length; index++) {
                let assignment = each_array_10[index];
                $$renderer3.push(`<li class="py-3 text-sm"><p class="font-semibold">${escape_html(assignment.task_ids?.join(", "))} · ${escape_html(assignment.account_name)}</p> <p class="mt-1 text-slate-300">${escape_html(assignment.actor)} · ${escape_html(assignment.available ? "Assigned" : "Unavailable or not permitted")}</p> `);
                if (assignment.available && relativeUrl(assignment.url)) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<a class="inline-flex min-h-11 items-center"${attr("href", relativeUrl(assignment.url))} target="_blank" rel="noopener noreferrer">Open assigned task workspace (new tab)</a>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> `);
                if (assignment.reason) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="mt-2 leading-6 text-slate-400">${escape_html(assignment.reason)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></li>`);
              }
              $$renderer3.push(`<!--]--></ul></details>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <details class="mt-3"><summary>Cases and actor handoffs</summary> <ul class="mt-2 divide-y divide-slate-800">`);
            const each_array_11 = ensure_array_like(selectedSet().cases ?? []);
            if (each_array_11.length !== 0) {
              $$renderer3.push("<!--[-->");
              for (let $$index_12 = 0, $$length = each_array_11.length; $$index_12 < $$length; $$index_12++) {
                let item = each_array_11[$$index_12];
                $$renderer3.push(`<li class="py-3 text-sm"><p class="font-semibold">${escape_html(item.name)}</p> <p class="mt-1 text-slate-300">${escape_html(item.state)} · ${escape_html(item.actor)}</p> `);
                if (item.task_ids?.length) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="mt-2 text-slate-300">Assigned tasks: ${escape_html(item.task_ids.join(", "))}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> `);
                if (item.reason) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="mt-2 leading-6 text-amber-200">${escape_html(item.reason)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> `);
                if (relativeUrl(item.url)) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<a class="inline-flex min-h-11 items-center"${attr("href", relativeUrl(item.url))} target="_blank" rel="noopener noreferrer">Open ${escape_html(item.actor)} workspace (new tab)</a>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> <!--[-->`);
                const each_array_12 = ensure_array_like(item.handoffs ?? []);
                for (let index = 0, $$length2 = each_array_12.length; index < $$length2; index++) {
                  let handoff = each_array_12[index];
                  if (handoff.available && relativeUrl(handoff.url) && handoff.url !== item.url) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<a class="mr-4 inline-flex min-h-11 items-center"${attr("href", relativeUrl(handoff.url))} target="_blank" rel="noopener noreferrer">${escape_html(handoff.label)} (new tab)</a>`);
                  } else if (!handoff.available && handoff.reason) {
                    $$renderer3.push("<!--[1-->");
                    $$renderer3.push(`<p class="mt-2 leading-6 text-amber-200">${escape_html(handoff.label)}: ${escape_html(handoff.reason)}</p>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]-->`);
                }
                $$renderer3.push(`<!--]--></li>`);
              }
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push(`<li class="py-3 text-sm text-slate-400">No assigned case returned.</li>`);
            }
            $$renderer3.push(`<!--]--></ul></details> <details class="mt-3"><summary>Documents and physical outputs</summary><ul class="mt-2 divide-y divide-slate-800">`);
            const each_array_13 = ensure_array_like(selectedSet().documents ?? []);
            if (each_array_13.length !== 0) {
              $$renderer3.push("<!--[-->");
              for (let index = 0, $$length = each_array_13.length; index < $$length; index++) {
                let item = each_array_13[index];
                $$renderer3.push(`<li class="py-3 text-sm"><p class="font-semibold">${escape_html(item.label)}</p><p class="mt-1 text-amber-200">Physical status: ${escape_html(item.physical_status)}</p><div class="flex flex-wrap gap-x-4">`);
                if (relativeUrl(item.url)) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<a class="inline-flex min-h-11 items-center"${attr("href", relativeUrl(item.url))} target="_blank" rel="noopener noreferrer">Open document (new tab)</a>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]-->`);
                if (relativeUrl(item.print_url)) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<a class="inline-flex min-h-11 items-center"${attr("href", relativeUrl(item.print_url))} target="_blank" rel="noopener noreferrer">Open normal print workflow (new tab)</a>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div></li>`);
              }
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push(`<li class="py-3 text-sm text-slate-400">No current document returned. Preparation does not prove printing or handover.</li>`);
            }
            $$renderer3.push(`<!--]--></ul></details> <details class="mt-3"><summary>Paired case exchange</summary><p class="mt-3 text-sm">${escape_html(selectedSet().paired_exchange?.status ?? "unverified")}</p><p class="mt-1 text-sm leading-6 text-slate-400">${escape_html(selectedSet().paired_exchange?.reason)}</p>`);
            if (relativeUrl(selectedSet().paired_exchange?.url)) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<a class="inline-flex min-h-11 items-center text-sm"${attr("href", relativeUrl(selectedSet().paired_exchange.url))} target="_blank" rel="noopener noreferrer">Open assigned exchange (new tab)</a>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
            if (actionFor("pair")) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                type: "button",
                variant: "outline",
                class: "mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                disabled: !permitted("pair"),
                onclick: () => review("pair"),
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(actionFor("pair").label)}`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----><p class="mt-2 text-sm leading-6 text-slate-400">${escape_html(actionFor("pair").reason)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></details> <details class="mt-3"><summary>Session timing and reminders</summary> <ul class="mt-3 space-y-3 text-sm">`);
            const each_array_14 = ensure_array_like(selectedSet().sessions ?? []);
            if (each_array_14.length !== 0) {
              $$renderer3.push("<!--[-->");
              for (let $$index_14 = 0, $$length = each_array_14.length; $$index_14 < $$length; $$index_14++) {
                let session = each_array_14[$$index_14];
                $$renderer3.push(`<li><p class="font-semibold">${escape_html(session.label)} · ${escape_html(session.status)}</p><p class="mt-1 leading-6 text-slate-300">Starts ${escape_html(timestamp(session.starts_at))}<br/>Ends ${escape_html(timestamp(session.ends_at))}</p></li>`);
              }
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push(`<li class="text-slate-400">No session in this practice set.</li>`);
            }
            $$renderer3.push(`<!--]--></ul> <label class="mt-4 block text-sm font-semibold"${attr("for", `${uid}-session`)}>Selected session`);
            $$renderer3.select(
              {
                id: `${uid}-session`,
                class: "mt-2 w-full min-w-0",
                value: sessionId,
                disabled: busy() || !current() || !selectedSet().sessions?.length
              },
              ($$renderer4) => {
                $$renderer4.push(`<!--[-->`);
                const each_array_15 = ensure_array_like(selectedSet().sessions ?? []);
                for (let $$index_15 = 0, $$length = each_array_15.length; $$index_15 < $$length; $$index_15++) {
                  let session = each_array_15[$$index_15];
                  $$renderer4.option({ value: String(session.id) }, ($$renderer5) => {
                    $$renderer5.push(`${escape_html(session.label)}`);
                  });
                }
                $$renderer4.push(`<!--]-->`);
              }
            );
            $$renderer3.push(`</label> <div class="mt-4 grid gap-5 sm:grid-cols-2"><div class="min-w-0"><label class="text-sm font-semibold"${attr("for", `${uid}-preset`)}>Timing preset`);
            $$renderer3.select(
              {
                id: `${uid}-preset`,
                class: "mt-2 w-full min-w-0",
                value: preset,
                disabled: busy()
              },
              ($$renderer4) => {
                $$renderer4.push(`<!--[-->`);
                const each_array_16 = ensure_array_like(presets);
                for (let $$index_16 = 0, $$length = each_array_16.length; $$index_16 < $$length; $$index_16++) {
                  let [key, label] = each_array_16[$$index_16];
                  $$renderer4.option({ value: key }, ($$renderer5) => {
                    $$renderer5.push(`${escape_html(label)}`);
                  });
                }
                $$renderer4.push(`<!--]-->`);
              }
            );
            $$renderer3.push(`</label><p class="mt-2 text-sm leading-6 text-slate-400">Changes this session only. No system-clock change or automatic reminder.</p>`);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
              disabled: !permitted("time") || !sessionId,
              onclick: () => review("time"),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(actionFor("time")?.label ?? "Review timing change")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----><p class="mt-2 text-sm leading-6 text-slate-400">${escape_html(actionFor("time")?.reason)}</p></div> <div class="min-w-0"><label class="text-sm font-semibold"${attr("for", `${uid}-window`)}>Reminder window`);
            $$renderer3.select(
              {
                id: `${uid}-window`,
                class: "mt-2 w-full min-w-0",
                value: windowKey,
                disabled: busy()
              },
              ($$renderer4) => {
                $$renderer4.push(`<!--[-->`);
                const each_array_17 = ensure_array_like(windows);
                for (let $$index_17 = 0, $$length = each_array_17.length; $$index_17 < $$length; $$index_17++) {
                  let key = each_array_17[$$index_17];
                  $$renderer4.option({ value: key }, ($$renderer5) => {
                    $$renderer5.push(`${escape_html(key)}`);
                  });
                }
                $$renderer4.push(`<!--]-->`);
              }
            );
            $$renderer3.push(`</label><p class="mt-2 text-sm leading-6 text-slate-400">Separate confirmation. Current notification policy and safe practice recipients still apply.</p>`);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
              disabled: !permitted("reminder") || !sessionId,
              onclick: () => review("reminder"),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(actionFor("reminder")?.label ?? "Review reminder dispatch")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----><p class="mt-2 text-sm leading-6 text-slate-400">${escape_html(actionFor("reminder")?.reason)}</p></div></div></details> <details class="mt-3"><summary>Actual notification delivery status</summary><ul class="mt-3 divide-y divide-slate-800">`);
            const each_array_18 = ensure_array_like(selectedSet().deliveries ?? []);
            if (each_array_18.length !== 0) {
              $$renderer3.push("<!--[-->");
              for (let $$index_18 = 0, $$length = each_array_18.length; $$index_18 < $$length; $$index_18++) {
                let delivery = each_array_18[$$index_18];
                $$renderer3.push(`<li class="py-3 text-sm"><p>${escape_html(delivery.window)} · ${escape_html(delivery.channel)} · ${escape_html(delivery.status)}</p><p class="mt-1 leading-6 text-slate-400">${escape_html(delivery.reason)}</p></li>`);
              }
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push(`<li class="py-3 text-sm text-slate-400">No delivery records returned. Queuing is not evidence of delivery.</li>`);
            }
            $$renderer3.push(`<!--]--></ul></details>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else if (!busy() && !error) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<p role="status" class="mt-5 text-sm text-slate-400">Preparation details are not loaded. Refresh to check the current prerequisites.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return confirmationOpen;
          },
          set open($$value) {
            confirmationOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                showCloseButton: pending !== "confirm",
                class: "max-h-[90dvh] overflow-y-auto border-slate-800 bg-slate-950 text-slate-100 print:hidden sm:max-w-xl",
                onOpenAutoFocus: (event) => {
                  event.preventDefault();
                  reviewDialog?.focus({ preventScroll: true });
                  if (reviewDialog) reviewDialog.scrollTop = 0;
                },
                onEscapeKeydown: (event) => {
                  if (pending === "confirm") event.preventDefault();
                },
                onInteractOutside: (event) => {
                  if (pending === "confirm") event.preventDefault();
                },
                get ref() {
                  return reviewDialog;
                },
                set ref($$value) {
                  reviewDialog = $$value;
                  $$settled = false;
                },
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Review ${escape_html(preview?.operation?.action ?? "operation")} impact`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            class: "text-slate-300",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Confirm only this reviewed operation. Cancelling changes no cases or sessions.`);
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
                  if (preview) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<!--[-->`);
                    const each_array_19 = ensure_array_like([
                      ["creates", "Creates"],
                      ["replaces", "Replaces or retires"],
                      ["preserves", "Preserves"]
                    ]);
                    for (let $$index_20 = 0, $$length = each_array_19.length; $$index_20 < $$length; $$index_20++) {
                      let [key, label] = each_array_19[$$index_20];
                      $$renderer5.push(`<section class="mt-2"><h3 class="text-sm font-semibold">${escape_html(label)}</h3><ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-300">`);
                      const each_array_20 = ensure_array_like(preview.impact?.[key] ?? []);
                      if (each_array_20.length !== 0) {
                        $$renderer5.push("<!--[-->");
                        for (let $$index_19 = 0, $$length2 = each_array_20.length; $$index_19 < $$length2; $$index_19++) {
                          let item = each_array_20[$$index_19];
                          $$renderer5.push(`<li>${escape_html(item)}</li>`);
                        }
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push(`<li>None reported</li>`);
                      }
                      $$renderer5.push(`<!--]--></ul></section>`);
                    }
                    $$renderer5.push(`<!--]--> <ul class="mt-3 space-y-2 text-sm"><!--[-->`);
                    const each_array_21 = ensure_array_like(preview.prerequisites ?? []);
                    for (let $$index_21 = 0, $$length = each_array_21.length; $$index_21 < $$length; $$index_21++) {
                      let item = each_array_21[$$index_21];
                      $$renderer5.push(`<li><span${attr_class(clsx(item.status === "available" ? "text-blue-200" : "text-amber-200"))}>${escape_html(item.label)} · ${escape_html(item.status)}</span><p class="mt-1 leading-6 text-slate-400">${escape_html(item.reason)}</p></li>`);
                    }
                    $$renderer5.push(`<!--]--></ul> <p class="mt-3 text-xs leading-5 text-slate-400">Operation ${escape_html(preview.operation.id)} · expires ${escape_html(timestamp(preview.operation.expires_at))}</p> `);
                    if (!preview.can_confirm && preview.reason) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p role="status" class="text-sm leading-6 text-amber-200">${escape_html(preview.reason)}</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (confirmationError) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p role="alert" class="break-words text-sm leading-6 text-rose-200">${escape_html(confirmationError)}</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (retryConfirmation) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p class="text-sm leading-6 text-amber-200">The outcome is unknown. Retry sends the same operation ID, not a new preparation.</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (Dialog_footer) {
                      $$renderer5.push("<!--[-->");
                      Dialog_footer($$renderer5, {
                        children: ($$renderer6) => {
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            class: "min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
                            disabled: pending === "confirm",
                            onclick: cancelReview,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Cancel`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!---->`);
                          Button($$renderer6, {
                            type: "button",
                            class: "min-h-11 bg-blue-600 text-white hover:bg-blue-500",
                            disabled: busy() || !preview.can_confirm || expired,
                            onclick: confirmOperation,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(pending === "confirm" ? "Confirming…" : retryConfirmation ? "Retry same operation" : `Confirm ${preview.operation.action}`)}`);
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
                  } else {
                    $$renderer5.push("<!--[-1-->");
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
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  PracticePreparationPanel as default
};
