import { h as head, e as escape_html, a as ensure_array_like, b as attr, B as Button, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
function AnnouncementForm($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      announcement = null,
      revisions = [],
      capabilities = { manage: false, publish: false, campus: false },
      publishers = [],
      programs = []
    } = $$props;
    function localInputValue(value) {
      if (!value) return "";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      const local = new Date(date.getTime() - date.getTimezoneOffset() * 6e4);
      return local.toISOString().slice(0, 16);
    }
    const form = useForm({
      publisher_key: announcement ? void 0 : publishers[0]?.key ?? "",
      program_id: announcement?.program_id ?? "",
      title: announcement?.title ?? "",
      summary: announcement?.summary ?? "",
      body: announcement?.body ?? "",
      priority: announcement?.priority ?? "normal",
      is_featured: announcement?.is_featured ?? false,
      starts_at: localInputValue(announcement?.starts_at),
      ends_at: localInputValue(announcement?.ends_at),
      request_id: ""
    });
    const isEdit = derived(() => Boolean(announcement));
    const isPublished = derived(() => announcement?.status === "published");
    const canSave = derived(() => announcement ? Boolean(announcement.can_edit) : Boolean(capabilities.manage));
    const selectedPublisher = derived(() => publishers.find((publisher) => publisher.key === form.publisher_key) ?? null);
    const selectedProgram = derived(() => programs.find((program) => String(program.id) === String(form.program_id)) ?? null);
    const titleLength = derived(() => String(form.title ?? "").length);
    const summaryLength = derived(() => String(form.summary ?? "").length);
    const bodyLength = derived(() => String(form.body ?? "").length);
    const breadcrumbs = [
      { label: "Communications" },
      { label: "Announcements", href: "/communications" },
      {
        label: announcement ? "Edit announcement" : "Create announcement"
      }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1kgpdc6", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(announcement ? "Edit announcement" : "Create announcement")} — Announcements</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto grid w-full max-w-6xl gap-6">`);
          PageHeaderBar($$renderer4, {
            title: announcement ? "Edit announcement" : "New announcement",
            description: isPublished() ? "Review the current notice and submit an attributable correction." : "Write a clear public update for an authorized office or academic program."
          });
          $$renderer4.push(`<!----> `);
          if (!canSave()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-destructive/30 bg-destructive/5 text-foreground rounded-lg border p-4 text-sm" role="alert">`);
            if (announcement?.status === "withdrawn") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`This announcement was withdrawn.
                        Its content and revision history are read-only.`);
            } else if (isPublished()) {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`You need publish capability to edit a
                        published announcement.`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`You do not have permission
                        to save announcements.`);
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (!isEdit() && publishers.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-4 text-sm" role="status">No authorized publishing sources are available for your
                    account.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (form.errors?.conflict || form.errors?.expected_revision || form.errors?.request_id) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="text-foreground rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert"><p class="font-semibold">This announcement changed before your save was accepted.</p> <p class="mt-1">Your text is still here. Review the current revision
                        before trying again.</p> `);
            Link($$renderer4, {
              href: "/communications",
              class: "text-primary mt-2 inline-flex min-h-[44px] items-center underline underline-offset-4",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Return to Announcements`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <form class="grid min-w-0 gap-6 lg:grid-cols-2"><section class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6" aria-labelledby="announcement-content-title"><div><h2 id="announcement-content-title" class="text-lg font-semibold">Announcement content</h2> <p class="text-muted-foreground mt-1 text-sm">Plain text only. Formatting is preserved when the
                            notice is read.</p></div> `);
          if (!isEdit()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2">`);
            Label($$renderer4, {
              for: "publisher_key",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Publishing source`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "publisher_key",
                value: form.publisher_key,
                disabled: !canSave() || form.processing,
                class: "border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
              },
              ($$renderer5) => {
                $$renderer5.push(`<!--[-->`);
                const each_array = ensure_array_like(publishers);
                for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                  let publisher = each_array[$$index];
                  $$renderer5.option({ value: publisher.key }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(publisher.label)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(` `);
            if (form.errors.publisher_key) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.publisher_key)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="space-y-1"><p class="text-muted-foreground text-xs font-medium">Publishing source</p> <p class="text-foreground text-sm font-semibold">${escape_html(announcement.publisher_label)}</p></div>`);
          }
          $$renderer4.push(`<!--]--> `);
          if (!isEdit()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="space-y-2">`);
            Label($$renderer4, {
              for: "program_id",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Delivery target`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            $$renderer4.select(
              {
                id: "program_id",
                value: form.program_id,
                disabled: !canSave() || form.processing,
                required: Boolean(selectedPublisher()?.requires_program),
                "aria-invalid": Boolean(form.errors.program_id),
                class: "border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
              },
              ($$renderer5) => {
                $$renderer5.option(
                  {
                    value: "",
                    disabled: Boolean(selectedPublisher()?.requires_program)
                  },
                  ($$renderer6) => {
                    $$renderer6.push(`General — no program`);
                  }
                );
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
            $$renderer4.push(` <p class="text-muted-foreground text-xs leading-5">${escape_html(selectedPublisher()?.requires_program ? "Choose the active program this academic source owns." : "General notices appear on the homepage. Choose a program to keep this notice within that program.")}</p> `);
            if (form.errors.program_id) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.program_id)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="space-y-1"><p class="text-muted-foreground text-xs font-medium">Delivery target</p> <p class="text-foreground text-sm font-semibold">${escape_html(announcement.program_code ? `${announcement.program_code}${selectedProgram()?.name ? ` — ${selectedProgram().name}` : ""}` : "General — no program")}</p> <p class="text-muted-foreground text-xs leading-5">Issuing source and delivery target cannot be changed after creation.</p></div>`);
          }
          $$renderer4.push(`<!--]--> <div class="space-y-2"><div class="flex items-center justify-between gap-2">`);
          Label($$renderer4, {
            for: "title",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Subject / short title`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----><span class="text-muted-foreground text-xs tabular-nums">${escape_html(titleLength())}/80</span></div> `);
          Input($$renderer4, {
            id: "title",
            maxlength: "80",
            required: true,
            "aria-invalid": Boolean(form.errors.title),
            disabled: !canSave() || form.processing,
            class: "min-h-[44px]",
            get value() {
              return form.title;
            },
            set value($$value) {
              form.title = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p class="text-muted-foreground text-xs leading-5">Use a clear, short headline for the ticker (about 6–10 words).</p> `);
          if (form.errors.title) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.title)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2"><div class="flex items-center justify-between gap-2">`);
          Label($$renderer4, {
            for: "summary",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Short summary`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----><span class="text-muted-foreground text-xs tabular-nums">${escape_html(summaryLength())}/160</span></div> <textarea id="summary" maxlength="160" required="" rows="3"${attr("aria-invalid", Boolean(form.errors.summary))}${attr("disabled", !canSave() || form.processing, true)} class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-20 w-full resize-y rounded-md border px-3 py-2 text-sm leading-6 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60">`);
          const $$body = escape_html(form.summary);
          if ($$body) {
            $$renderer4.push(`${$$body}`);
          }
          $$renderer4.push(`</textarea> `);
          if (form.errors.summary) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.summary)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2"><div class="flex items-center justify-between gap-2">`);
          Label($$renderer4, {
            for: "body",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Full announcement`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----><span class="text-muted-foreground text-xs tabular-nums">${escape_html(bodyLength())}/10,000</span></div> <textarea id="body" maxlength="10000" required="" rows="10"${attr("aria-invalid", Boolean(form.errors.body))}${attr("disabled", !canSave() || form.processing, true)} class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-64 w-full resize-y rounded-md border px-3 py-2 text-sm leading-6 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60">`);
          const $$body_1 = escape_html(form.body);
          if ($$body_1) {
            $$renderer4.push(`${$$body_1}`);
          }
          $$renderer4.push(`</textarea> `);
          if (form.errors.body) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.body)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></section> <div class="min-w-0 space-y-6"><section class="border-border bg-card/80 space-y-5 rounded-lg border p-4 sm:p-6" aria-labelledby="publication-settings-title"><div><h2 id="publication-settings-title" class="text-lg font-semibold">Publication settings</h2> <p class="text-muted-foreground mt-1 text-sm">Publishing happens from the communications list
                                after saving.</p></div> <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2">`);
          Label($$renderer4, {
            for: "starts_at",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Starts at`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "starts_at",
            type: "datetime-local",
            disabled: !canSave() || form.processing,
            "aria-invalid": Boolean(form.errors.starts_at),
            "aria-describedby": "starts-at-help",
            class: "min-h-[44px]",
            get value() {
              return form.starts_at;
            },
            set value($$value) {
              form.starts_at = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p id="starts-at-help" class="text-muted-foreground text-xs">Leave blank to start when the notice is
                                    published.</p> `);
          if (form.errors.starts_at) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.starts_at)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "ends_at",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Ends at (optional)`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "ends_at",
            type: "datetime-local",
            disabled: !canSave() || form.processing,
            "aria-invalid": Boolean(form.errors.ends_at),
            class: "min-h-[44px]",
            get value() {
              return form.ends_at;
            },
            set value($$value) {
              form.ends_at = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.ends_at) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.ends_at)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "priority",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Priority`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "priority",
              value: form.priority,
              disabled: !canSave() || form.processing,
              class: "border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "normal" }, ($$renderer6) => {
                $$renderer6.push(`Normal`);
              });
              $$renderer5.option({ value: "urgent" }, ($$renderer6) => {
                $$renderer6.push(`Urgent`);
              });
            }
          );
          $$renderer4.push(` `);
          if (form.errors.priority) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.priority)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <label class="flex min-h-[44px] items-center gap-3 text-sm font-medium"><input type="checkbox"${attr("checked", form.is_featured, true)}${attr("disabled", !canSave() || form.processing, true)} class="border-input accent-primary size-4 rounded"/> Include in featured alerts when published</label> `);
          if (form.errors.is_featured) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(form.errors.is_featured)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></section> <section class="border-border bg-muted/25 space-y-3 rounded-lg border p-4 sm:p-6" aria-labelledby="announcement-preview-title"><h2 id="announcement-preview-title" class="text-lg font-semibold">Preview</h2> <article class="border-border/70 min-w-0 border-t pt-4"><p class="text-muted-foreground text-xs">${escape_html(announcement?.publisher_label ?? selectedPublisher()?.label ?? "Publishing source")}</p> <p class="text-muted-foreground mt-1 text-xs">${escape_html(announcement ? announcement.program_code ? `${announcement.program_code} program notice` : "General notice" : selectedProgram() ? `${selectedProgram().code} program notice` : selectedPublisher()?.requires_program ? "Select a required program" : "General notice")}</p> <h3 class="text-foreground mt-2 text-xl font-semibold break-words">${escape_html(form.title || "Subject preview")}</h3> <p class="text-foreground mt-2 text-sm leading-6 break-words whitespace-pre-wrap">${escape_html(form.summary || "Your short summary will appear here.")}</p> <p class="text-muted-foreground mt-3 text-sm leading-6 break-words whitespace-pre-wrap">${escape_html(form.body || "The full announcement preview will appear here.")}</p></article></section> `);
          if (isEdit() && revisions.length > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="border-border bg-card/80 rounded-lg border p-4 sm:p-6" aria-labelledby="revision-history-title"><h2 id="revision-history-title" class="text-lg font-semibold">Revision history</h2> <ol class="divide-border/60 mt-4 divide-y"><!--[-->`);
            const each_array_2 = ensure_array_like(revisions);
            for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
              let revision = each_array_2[$$index_2];
              $$renderer4.push(`<li class="py-4 first:pt-0 last:pb-0"><div class="flex flex-wrap items-baseline justify-between gap-2"><h3 class="font-semibold">Revision ${escape_html(revision.revision)}: ${escape_html(revision.title)}</h3> <time class="text-muted-foreground text-xs"${attr("datetime", revision.created_at)}>${escape_html(new Date(revision.created_at).toLocaleString())}</time></div> <p class="text-muted-foreground mt-1 text-sm">${escape_html(revision.status)} · ${escape_html(revision.actor_name)}</p> `);
              if (revision.reason) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-muted-foreground mt-2 text-sm whitespace-pre-wrap">${escape_html(revision.reason)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> <p class="text-foreground mt-2 text-sm whitespace-pre-wrap">${escape_html(revision.summary)}</p> <details class="mt-2 text-sm"><summary class="cursor-pointer font-medium">Read full revision</summary> <p class="mt-2 break-words whitespace-pre-wrap">${escape_html(revision.body)}</p></details></li>`);
            }
            $$renderer4.push(`<!--]--></ol></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">`);
          Link($$renderer4, {
            href: "/communications",
            class: "border-border inline-flex min-h-[44px] items-center justify-center rounded-lg border px-4 text-sm font-medium",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "submit",
            disabled: !canSave() || !isEdit() && publishers.length === 0 || form.processing,
            class: "min-h-[44px]",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Saving…" : isPublished() ? "Save correction" : isEdit() ? "Save draft" : "Save announcement")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> `);
          if (form.errors?.message) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="alert" class="text-destructive text-sm">${escape_html(form.errors.message)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></form></div>`);
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
  AnnouncementForm as default
};
