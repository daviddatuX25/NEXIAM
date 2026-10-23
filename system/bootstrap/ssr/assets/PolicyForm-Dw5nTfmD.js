import { h as head, e as escape_html, a as ensure_array_like, b as attr, B as Button, d as derived, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function PolicyForm($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      policy = null,
      revisions = [],
      publishers = [],
      programs = []
    } = $$props;
    function initialProgramId() {
      const publisher = publishers[0];
      const code = publisher?.requires_program ? publisher.key.slice("program:".length) : "";
      return policy?.program_id ?? (programs.find((program) => program.code === code)?.id ?? "");
    }
    const form = useForm({
      publisher_key: policy ? void 0 : publishers[0]?.key ?? "",
      program_id: initialProgramId(),
      title: policy?.title ?? "",
      content: policy?.content ?? "",
      request_id: ""
    });
    let requestSignature = "";
    let requestToken = "";
    const isWithdrawn = derived(() => policy?.status === "withdrawn");
    const canSave = derived(() => !isWithdrawn() && (policy ? policy.can_manage : publishers.length > 0));
    const canPublish = derived(() => Boolean(policy?.can_publish && !isWithdrawn()));
    const selectedPublisher = derived(() => publishers.find((publisher) => publisher.key === form.publisher_key) ?? null);
    const titleLength = derived(() => String(form.title ?? "").length);
    const contentLength = derived(() => String(form.content ?? "").length);
    const documentDirty = derived(() => Boolean(policy) && (String(form.title ?? "").trim() !== policy.title || String(form.content ?? "").trim() !== policy.content));
    const hasPendingDraft = derived(() => Boolean(policy) && policy.revision > (policy.published_revision ?? 0));
    function changePublisher(event) {
      const publisherKey = event.currentTarget.value;
      form.publisher_key = publisherKey;
      const publisher = publishers.find((item) => item.key === publisherKey);
      const code = publisher?.requires_program ? publisher.key.slice("program:".length) : "";
      form.program_id = programs.find((program) => program.code === code)?.id ?? "";
    }
    function stableRequestId(payload) {
      const signature = JSON.stringify(payload);
      if (signature !== requestSignature) {
        requestSignature = signature;
        requestToken = newIdempotencyKey();
      }
      return requestToken;
    }
    function publish() {
      const payload = { expected_revision: policy.revision };
      form.request_id = stableRequestId(payload);
      form.transform(() => ({
        request_id: form.request_id,
        expected_revision: policy.revision
      }));
      form.post(`/communications/policies/${policy.id}/publish`, {
        preserveScroll: true,
        onSuccess: () => {
          requestSignature = "";
          requestToken = "";
        }
      });
    }
    const breadcrumbs = [
      { label: "Communications" },
      { label: "Public policies", href: "/communications/policies" },
      { label: policy ? "Edit policy" : "Create policy" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("18a4852", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(policy ? "Edit public policy" : "Create public policy")} — Public policies</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto grid w-full max-w-6xl gap-6">`);
          PageHeaderBar($$renderer4, {
            title: policy ? "Edit public policy" : "New public policy",
            description: "Draft plain-text documents for your authorized office or owned active program. Publishing is a separate action."
          });
          $$renderer4.push(`<!----> `);
          if (isWithdrawn()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-lg border border-border bg-muted/30 p-4 text-sm" role="status">This policy was withdrawn. Its full content and publication history are read-only. Create a new policy for a replacement.</div>`);
          } else if (!canSave()) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm" role="alert">You do not have the required current authority to edit this policy.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors?.expected_revision || form.errors?.request_id || form.errors?.conflict) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert"><p class="font-semibold">This policy changed or the request could not be replayed.</p> <p class="mt-1">Your text remains here. Reload the current revision before retrying, and use a new request ID for a changed command.</p> `);
            Link($$renderer4, {
              href: "/communications/policies",
              class: "mt-2 inline-block underline",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Return to policy list`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <form class="grid min-w-0 gap-6 lg:grid-cols-2"><section class="min-w-0 space-y-5 rounded-lg border border-border bg-card/70 p-4 sm:p-6"><h2 class="text-lg font-semibold">Policy details</h2> `);
          if (!policy) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2">`);
            Label($$renderer4, {
              for: "publisher_key",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Issuing office or program`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "publisher_key",
                value: form.publisher_key,
                onchange: changePublisher,
                disabled: form.processing || publishers.length < 2,
                class: "border-input bg-background min-h-11 w-full rounded-md border px-3 py-2"
              },
              ($$renderer5) => {
                $$renderer5.push(`<!--[-->`);
                const each_array = ensure_array_like(publishers);
                for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                  let publisher = each_array[$$index];
                  $$renderer5.option({ value: publisher.key }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(publisher.label)}${escape_html(publisher.requires_program ? " — own program" : " office")}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(` `);
            if (form.errors.publisher_key) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.publisher_key)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (selectedPublisher() && !selectedPublisher().requires_program) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-2">`);
              Label($$renderer4, {
                for: "program_id",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Public placement`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              $$renderer4.select(
                {
                  id: "program_id",
                  value: form.program_id,
                  disabled: form.processing,
                  class: "border-input bg-background min-h-11 w-full rounded-md border px-3 py-2"
                },
                ($$renderer5) => {
                  $$renderer5.option({ value: "" }, ($$renderer6) => {
                    $$renderer6.push(`General policy`);
                  });
                  $$renderer5.push(`<!--[-->`);
                  const each_array_1 = ensure_array_like(programs);
                  for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                    let program = each_array_1[$$index_1];
                    $$renderer5.option({ value: String(program.id) }, ($$renderer6) => {
                      $$renderer6.push(`${escape_html(program.code)} — ${escape_html(program.name)}`);
                    });
                  }
                  $$renderer5.push(`<!--]-->`);
                }
              );
              $$renderer4.push(` `);
              if (form.errors.program_id) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.program_id)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div>`);
            } else if (selectedPublisher()?.requires_program) {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`<p class="rounded-md border border-border bg-muted/30 p-3 text-sm">This policy will appear only on the owned active program page.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<dl class="grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-muted-foreground">Issuer</dt><dd class="font-medium">${escape_html(policy.publisher_label)}</dd></div><div><dt class="text-muted-foreground">Placement</dt><dd class="font-medium">${escape_html(policy.program_code ? `${policy.program_code} program page` : "General")}</dd></div><div><dt class="text-muted-foreground">Status</dt><dd class="font-medium capitalize">${escape_html(policy.status)}</dd></div><div><dt class="text-muted-foreground">Draft revision</dt><dd class="font-medium">${escape_html(policy.revision)}</dd></div></dl>`);
          }
          $$renderer4.push(`<!--]--> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "title",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Title`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "title",
            maxlength: "160",
            required: true,
            disabled: !canSave() || form.processing,
            "aria-describedby": "policy-title-count",
            get value() {
              return form.title;
            },
            set value($$value) {
              form.title = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <div class="flex justify-between text-xs text-muted-foreground"><span id="policy-title-count">1–160 characters</span><span>${escape_html(titleLength())}/160</span></div> `);
          if (form.errors.title) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.title)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "content",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Policy text`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <textarea id="content" maxlength="10000" required=""${attr("disabled", !canSave() || form.processing, true)} aria-describedby="policy-content-count" class="border-input bg-background min-h-[22rem] w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring" placeholder="Enter plain-text policy content.">`);
          const $$body = escape_html(form.content);
          if ($$body) {
            $$renderer4.push(`${$$body}`);
          }
          $$renderer4.push(`</textarea> <div class="flex justify-between text-xs text-muted-foreground"><span id="policy-content-count">1–10,000 characters · plain text</span><span>${escape_html(contentLength())}/10000</span></div> `);
          if (form.errors.content) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.content)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (form.errors.status) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="alert" class="text-sm text-destructive">${escape_html(form.errors.status)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex flex-wrap justify-end gap-3 pt-2">`);
          Link($$renderer4, {
            href: "/communications/policies",
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          if (canSave()) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              type: "submit",
              disabled: form.processing,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(form.processing ? "Saving…" : policy ? "Save draft changes" : "Save draft")}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (canPublish() && policy) {
            $$renderer4.push("<!--[0-->");
            Button($$renderer4, {
              type: "button",
              variant: "default",
              disabled: form.processing || documentDirty() || !hasPendingDraft(),
              onclick: publish,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Publish saved revision`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (policy?.status === "published") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-xs text-muted-foreground">Saving changes does not alter the published document. Use “Publish saved revision” to make this successor public.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></section> <aside class="min-w-0 space-y-4 rounded-lg border border-border bg-card/50 p-4 sm:p-6"><h2 class="text-lg font-semibold">Version history</h2> `);
          if (revisions.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-muted-foreground">No saved revisions yet.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<ol class="divide-y divide-border/60" aria-label="Immutable policy revision history"><!--[-->`);
            const each_array_2 = ensure_array_like(revisions);
            for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
              let revision = each_array_2[$$index_2];
              $$renderer4.push(`<li class="space-y-2 py-4 first:pt-0"><div class="flex flex-wrap items-center justify-between gap-2"><strong>Revision ${escape_html(revision.revision)} · <span class="capitalize">${escape_html(revision.state)}</span></strong><span class="text-xs text-muted-foreground">${escape_html(revision.actor_name)} · ${escape_html(revision.created_at)}</span></div><h3 class="font-medium">${escape_html(revision.title)}</h3><p class="whitespace-pre-wrap break-words text-sm">${escape_html(revision.content)}</p>`);
              if (revision.reason) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-sm text-muted-foreground">Reason: ${escape_html(revision.reason)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></li>`);
            }
            $$renderer4.push(`<!--]--></ol>`);
          }
          $$renderer4.push(`<!--]--></aside></form></div>`);
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
  PolicyForm as default
};
