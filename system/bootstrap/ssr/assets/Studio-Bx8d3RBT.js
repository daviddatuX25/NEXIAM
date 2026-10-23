import { h as head, e as escape_html, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
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
function Studio($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      family,
      versions = [],
      domain,
      back_url,
      authoring_mode = "builtin",
      can_edit = false,
      builtin_message = null,
      builtin_inspection = null,
      draft = null,
      endpoints = {}
    } = $$props;
    const isBuiltin = derived(() => authoring_mode === "builtin");
    const message = derived(() => builtin_message && typeof builtin_message === "object" ? builtin_message : null);
    const inspectionTitle = derived(() => builtin_inspection?.title ?? null);
    const inspectionBody = derived(() => builtin_inspection?.body ?? null);
    const breadcrumbs = derived(() => [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      { label: `${domain} notices`, href: back_url },
      { label: family.name }
    ]);
    head("6la65i", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(family.name)} · ${escape_html(isBuiltin() ? "Notice configuration" : "Notice metadata")}</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs: breadcrumbs(),
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        PageHeaderBar($$renderer3, {
          title: family.name,
          description: isBuiltin() ? "Developer-configured system message. This family is read-only." : "Message details are hidden for this metadata-only view."
        });
        $$renderer3.push(`<!----> <div class="flex flex-wrap gap-2" aria-label="Family authoring capabilities">`);
        Badge($$renderer3, {
          variant: isBuiltin() ? "secondary" : "outline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(isBuiltin() ? "System message · read only" : "Metadata only")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        if (!can_edit) {
          $$renderer3.push("<!--[0-->");
          Badge($$renderer3, {
            variant: "secondary",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->No authoring access`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (!isBuiltin() && can_edit && endpoints?.edit) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap gap-3">`);
          Link($$renderer3, {
            href: endpoints.edit,
            role: "button",
            class: "inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(draft ? "Resume draft" : "Edit message")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (builtin_inspection) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="builtin-archive-heading"><div class="flex flex-wrap items-center justify-between gap-2"><h2 id="builtin-archive-heading" class="text-base font-semibold">Protected historical inspection</h2> `);
          Badge($$renderer3, {
            variant: "secondary",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(builtin_inspection.kind === "draft" ? "Retained draft" : `Published version ${builtin_inspection.version ?? "unavailable"}`)}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> <p class="text-sm text-muted-foreground">This historical content is read-only and does not alter the configured message, publication pointer, or deliveries.</p> `);
          if (inspectionTitle() !== null) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div><h3 class="text-sm font-medium">Historical title</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(inspectionTitle())}</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (inspectionBody() !== null) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div><h3 class="text-sm font-medium">Historical body</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(inspectionBody())}</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (inspectionTitle() === null && inspectionBody() === null) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-sm text-muted-foreground">No historical title or body is available in this projection.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></section>`);
        } else if (isBuiltin()) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<section class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="configured-message-heading"><div class="flex flex-wrap items-start justify-between gap-2"><div><h2 id="configured-message-heading" class="text-base font-semibold">Configured message</h2><p class="mt-1 text-sm text-muted-foreground">Developer-owned copy. No message is created or edited here.</p></div> `);
          Badge($$renderer3, {
            variant: "secondary",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Read only`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> `);
          if (message()?.purpose ?? family.purpose) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div><h3 class="text-sm font-medium">Purpose</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(message()?.purpose ?? family.purpose)}</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (family.type ?? family.message_type) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div><h3 class="text-sm font-medium">Type</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(family.type ?? family.message_type)}</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (message()) {
            $$renderer3.push("<!--[0-->");
            if (message().title) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div><h3 class="text-sm font-medium">Configured title</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(message().title)}</p></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (message().body) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div><h3 class="text-sm font-medium">Configured body</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">${escape_html(message().body)}</p></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (message().variants?.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="space-y-3" aria-labelledby="configured-variants-heading"><h3 id="configured-variants-heading" class="text-sm font-semibold">Named message variants</h3> <!--[-->`);
              const each_array = ensure_array_like(message().variants);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let variant = each_array[$$index];
                $$renderer3.push(`<article class="min-w-0 space-y-2 rounded-lg border border-border/70 p-3"><h4 class="break-words text-sm font-medium">${escape_html(variant.label)}</h4> `);
                if (variant.title) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="break-words whitespace-pre-wrap text-sm">${escape_html(variant.title)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> `);
                if (variant.body) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="break-words whitespace-pre-wrap text-sm text-muted-foreground">${escape_html(variant.body)}</p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></article>`);
              }
              $$renderer3.push(`<!--]--></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<p class="text-sm text-muted-foreground">Configured copy has no named variants.</p>`);
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<p class="text-sm text-muted-foreground">Purpose/type metadata is available, but configured message copy is not available to this reader.</p>`);
          }
          $$renderer3.push(`<!--]--></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<section class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="notice-metadata-heading"><h2 id="notice-metadata-heading" class="text-base font-semibold">Notice metadata</h2> <p class="text-sm text-muted-foreground">This view does not expose message content, assets, authoring state, or document sources.</p></section>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (!builtin_inspection && draft) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4" aria-label="Retained draft"><div class="min-w-0 space-y-2"><h2 class="text-base font-semibold">Retained historical draft</h2><p class="text-sm text-muted-foreground">Draft retained · ${escape_html(draft.updated_at ?? "Updated time unavailable")}</p></div> `);
          if (draft.inspect_url) {
            $$renderer3.push("<!--[0-->");
            Link($$renderer3, {
              href: draft.inspect_url,
              class: "inline-flex min-h-10 items-center rounded-md border px-3 text-sm",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Inspect retained draft`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <section class="min-w-0 space-y-3" aria-labelledby="published-versions"><h2 id="published-versions" class="text-lg font-semibold">Retained published history</h2> <div class="divide-y divide-border rounded-xl border border-border bg-card">`);
        const each_array_1 = ensure_array_like(versions);
        if (each_array_1.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let version = each_array_1[$$index_1];
            $$renderer3.push(`<article class="grid min-w-0 gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"><div class="space-y-2"><div class="flex flex-wrap gap-2"><h3 class="text-base font-semibold">Version ${escape_html(version.version)}</h3>`);
            Badge($$renderer3, {
              variant: version.version === family.active_version ? "outline" : "secondary",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(version.version === family.active_version ? "Current published" : "Historical")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div><p class="break-words text-sm text-muted-foreground">Published ${escape_html(version.created_at ?? "time unavailable")}</p></div> `);
            if (version.inspect_url) {
              $$renderer3.push("<!--[0-->");
              Link($$renderer3, {
                href: version.inspect_url,
                class: "inline-flex min-h-10 items-center rounded-md border px-3 text-sm",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Inspect version ${escape_html(version.version)}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></article>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<p class="p-5 text-sm text-muted-foreground">No published versions are available. This does not change the configured message or claim a delivery.</p>`);
        }
        $$renderer3.push(`<!--]--></div></section> `);
        Link($$renderer3, {
          href: back_url,
          class: "inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to ${escape_html(domain)} notices`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div>`);
      }
    });
  });
}
export {
  Studio as default
};
