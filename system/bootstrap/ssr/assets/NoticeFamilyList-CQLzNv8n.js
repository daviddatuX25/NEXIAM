import { b as attr, e as escape_html, a as ensure_array_like, d as derived } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { L as Link } from "./Link-B7ks7rBK.js";
function NoticeFamilyList($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { notices = [], domain, canEdit = false } = $$props;
    let search = "";
    const filtered = derived(() => notices.filter((notice) => `${notice.name} ${notice.description ?? ""}`.toLowerCase().includes(search.trim().toLowerCase())));
    $$renderer2.push(`<section class="min-w-0 space-y-4"${attr("aria-labelledby", `families-${domain}`)}><div class="flex flex-wrap items-end justify-between gap-3"><div><h2${attr("id", `families-${domain}`)} class="text-lg font-semibold">Notice families</h2><p class="mt-1 text-sm text-muted-foreground">Choose a canonical notice to inspect immutable versions and its sole draft.</p></div> <label class="block w-full text-sm font-medium sm:w-72"${attr("for", `notice-search-${domain}`)}>Find a notice <input${attr("id", `notice-search-${domain}`)} type="search"${attr("value", search)} class="mt-1 min-h-11 w-full rounded-md border border-border bg-background px-3 text-sm"/></label></div> <p class="sr-only" role="status">${escape_html(filtered().length)} matching notice families</p> <div class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card" aria-label="Notice families">`);
    const each_array = ensure_array_like(filtered());
    if (each_array.length !== 0) {
      $$renderer2.push("<!--[-->");
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let notice = each_array[$$index];
        $$renderer2.push(`<article class="grid min-w-0 gap-4 p-4 transition-colors hover:bg-muted/25 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"><div class="min-w-0 space-y-3"><div><h3 class="break-words text-base font-semibold sm:text-lg">`);
        Link($$renderer2, {
          href: `/setup/notifications/${domain}/templates/${notice.slug}`,
          class: "underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(notice.name)}`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----></h3> <p class="mt-1 max-w-3xl break-words text-sm text-muted-foreground">${escape_html(notice.description ?? "No description supplied.")}</p></div> <div class="flex flex-wrap gap-2">`);
        Badge($$renderer2, {
          variant: notice.active_version ? "outline" : "secondary",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(notice.active_version ? `Published version ${notice.active_version}` : "Not yet published")}`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----> `);
        Badge($$renderer2, {
          variant: notice.has_draft ? "secondary" : "outline",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(notice.has_draft ? "Draft available" : "No draft")}`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----> `);
        if (notice.authoring_mode === "email") {
          $$renderer2.push("<!--[0-->");
          Badge($$renderer2, {
            variant: "outline",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->Email authoring · no PDF`);
            },
            $$slots: { default: true }
          });
        } else if (notice.authoring_mode === "email_pdf") {
          $$renderer2.push("<!--[1-->");
          Badge($$renderer2, {
            variant: "outline",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->Email authoring · optional PDF`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer2.push("<!--[-1-->");
          Badge($$renderer2, {
            variant: "secondary",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->Configured message · read only`);
            },
            $$slots: { default: true }
          });
        }
        $$renderer2.push(`<!--]--></div></div> <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">`);
        Link($$renderer2, {
          href: notice.authoring_mode === "builtin" || !canEdit ? `/setup/notifications/${domain}/templates/${notice.slug}` : `/setup/notifications/${domain}/templates/${notice.slug}/edit`,
          class: "inline-flex min-h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:w-auto",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(notice.authoring_mode === "builtin" || !canEdit ? "View" : "Edit message")}`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----> `);
        if (notice.active_version > 0) {
          $$renderer2.push("<!--[0-->");
          Link($$renderer2, {
            href: `/setup/notifications/${domain}/templates/${notice.slug}#published-versions`,
            class: "inline-flex min-h-10 w-full items-center justify-center px-3 text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-auto",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->Inspect history`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div></article>`);
      }
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push(`<p class="p-5 text-sm text-muted-foreground">${escape_html(search.trim() ? "No families match this search. Clear the search to see all notices." : "No notice families are configured for this domain. This is not a delivery or preview result.")}</p>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    if (!canEdit) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-sm text-muted-foreground">Metadata-only view. Private configured copy and historical content require full-source access; authoring also depends on the family’s server-owned type.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
export {
  NoticeFamilyList as N
};
