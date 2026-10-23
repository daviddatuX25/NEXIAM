import { h as head, e as escape_html, a as ensure_array_like, b as attr, B as Button, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
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
import "./badge-D9Vgs7xh.js";
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
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function ConsultationPolicy($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      academic_cycles = [],
      selected_cycle_id = null,
      policy = null,
      return_to = null
    } = $$props;
    const page = usePage();
    const status = derived(() => page.props.flash?.status ?? "");
    const errors = derived(() => page.props.errors ?? {});
    let normalMethod = policy?.normal_method ?? "online";
    let allowOnlineExceptions = Boolean(policy?.allow_online_exceptions);
    let saving = false;
    let saveError = "";
    const dirty = derived(() => normalMethod !== (policy?.normal_method ?? "online") || normalMethod === "f2f" && allowOnlineExceptions !== Boolean(policy?.allow_online_exceptions));
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Consultation Policy" }
    ];
    function canLeave() {
      if (dirty() && !window.confirm("Discard unsaved consultation policy changes and leave this page?")) {
        saveError = "Unsaved policy changes were kept.";
        return false;
      }
      return true;
    }
    function navigate(url, onBlocked = null) {
      if (!url || !canLeave()) {
        onBlocked?.();
        return;
      }
      router.visit(url, {
        onFinish: () => {
        }
      });
    }
    function selectCycle(event) {
      const select = event.currentTarget;
      const params = new URLSearchParams();
      const cycleId = select.value;
      if (cycleId) params.set("cycle_id", cycleId);
      if (return_to) params.set("return_to", return_to);
      const query = params.toString();
      navigate(`/setup/consultation-policy${query ? `?${query}` : ""}`, () => {
        select.value = String(selected_cycle_id ?? "");
      });
    }
    head("mhr46a", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Consultation Policy · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 pb-40 sm:p-6 sm:pb-40">`);
        {
          let actions = function($$renderer4) {
            if (return_to) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                variant: "outline",
                class: "min-h-10 gap-2",
                onclick: () => navigate(return_to),
                children: ($$renderer5) => {
                  Arrow_left($$renderer5, { class: "size-4", "aria-hidden": "true" });
                  $$renderer5.push(`<!----> <span>Return to Consultation</span>`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          };
          PageHeaderBar($$renderer3, {
            title: "Consultation Policy",
            description: "Choose the normal consultation method for this academic cycle.",
            actions
          });
        }
        $$renderer3.push(`<!----> `);
        if (status()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">${escape_html(status())}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (saveError || Object.keys(errors()).length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">`);
          if (saveError) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p>${escape_html(saveError)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <!--[-->`);
          const each_array = ensure_array_like(Object.values(errors()));
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let error = each_array[$$index];
            $$renderer3.push(`<p>${escape_html(error)}</p>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (academic_cycles.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-2">`);
          Label($$renderer3, {
            for: "cycle-select",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Academic cycle`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          $$renderer3.select(
            {
              id: "cycle-select",
              value: selected_cycle_id ?? "",
              onchange: selectCycle,
              class: "h-10 min-w-64 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs"
            },
            ($$renderer4) => {
              $$renderer4.push(`<!--[-->`);
              const each_array_1 = ensure_array_like(academic_cycles);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let cycle = each_array_1[$$index_1];
                $$renderer4.option({ value: cycle.id }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(cycle.label)}${escape_html(cycle.is_active ? " · Active" : "")}`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(`</div> <section aria-labelledby="consultation-policy-heading" class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs"><div class="border-b border-border/60 bg-muted/20 p-5 sm:p-6"><h2 id="consultation-policy-heading" class="text-base font-semibold text-foreground">Consultation method</h2> <p class="mt-1 text-sm text-muted-foreground">The default applies the consultation preference for the cycle. Staff explicitly start each online encounter; saving this policy never converts bookings or changes results.</p></div> <form class="space-y-5 p-5 sm:p-6"><fieldset class="space-y-3"><legend class="text-sm font-medium text-foreground">Normal method</legend> <label for="normal-method-online" class="flex items-start gap-3 text-sm text-foreground"><input id="normal-method-online" type="radio" name="normal-method" value="online"${attr("checked", normalMethod === "online", true)} class="mt-0.5 size-4 accent-primary"/> <span><span class="font-medium">Online</span> <span class="mt-1 block text-muted-foreground">Online is the default. Staff explicitly start each online encounter when appropriate.</span></span></label> <label for="normal-method-f2f" class="flex items-start gap-3 text-sm text-foreground"><input id="normal-method-f2f" type="radio" name="normal-method" value="f2f"${attr("checked", normalMethod === "f2f", true)} class="mt-0.5 size-4 accent-primary"/> <span><span class="font-medium">Face-to-face</span> <span class="mt-1 block text-muted-foreground">Face-to-face is the normal method. Existing bookings and results are not changed.</span></span></label></fieldset> `);
          if (normalMethod === "f2f") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<label for="allow-online-exceptions" class="flex max-w-2xl items-start gap-3 text-sm text-foreground"><input id="allow-online-exceptions" type="checkbox"${attr("checked", allowOnlineExceptions, true)} class="mt-0.5 size-4 rounded border-input accent-primary"/> <span><span class="font-medium">Allow online exceptions</span> <span class="mt-1 block text-muted-foreground">When enabled, staff may explicitly start an online encounter for an individual applicant.</span></span></label>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          Button($$renderer3, {
            type: "submit",
            disabled: saving,
            "aria-busy": saving,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html("Save")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></form></section> `);
          if (!policy?.media_ready) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p role="note" class="text-sm text-muted-foreground"${attr("title", policy?.media_unavailable_reason ?? void 0)}>Online consultation is temporarily unavailable. Face-to-face consultation remains available.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<section class="rounded-xl border border-border/70 bg-card p-6 text-sm text-muted-foreground">Create an academic cycle before configuring its consultation method.</section>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  ConsultationPolicy as default
};
