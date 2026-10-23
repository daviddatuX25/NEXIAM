import { r as run, o as onDestroy, h as head, e as escape_html, a as ensure_array_like, b as attr, c as attr_class, f as attr_style, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import "clsx";
import MessageRuns from "./MessageRuns-C-ty_l7J.js";
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
const NOTICE_PREVIEW_CSP = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; navigate-to 'none'";
function noticePreviewDocument(html) {
  let head2 = "";
  let body = html;
  if (typeof DOMParser !== "undefined") {
    const document = new DOMParser().parseFromString(html, "text/html");
    for (const link of document.querySelectorAll("a,area")) {
      link.removeAttribute("href");
      link.removeAttribute("target");
    }
    for (const node of document.querySelectorAll('script,base,meta[http-equiv="refresh"]')) {
      node.remove();
    }
    head2 = document.head.innerHTML;
    body = document.body.innerHTML;
  }
  return `<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="${NOTICE_PREVIEW_CSP}"><meta name="referrer" content="no-referrer">${head2}</head><body>${body}</body></html>`;
}
function Message($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      family,
      state: initialState,
      draft = null,
      base_version,
      content_sha256 = null,
      output_digests,
      tokens = [],
      assets = [],
      sample_context = {},
      endpoints,
      document_versions = [],
      versions = [],
      copy_source_version = null,
      supports_pdf = family.supports_pdf === true,
      read_only = false
    } = $$props;
    let previewView = "email";
    let mobileTab = "write";
    let snapshot = null;
    let previewWidth = 390;
    let busy = false;
    let blockIndex = 0;
    let imageBusy = false;
    let composerState = run(() => cloneMessageData(initialState));
    function cloneMessageData(value) {
      return JSON.parse(JSON.stringify(value));
    }
    onDestroy(() => {
      for (const asset of ownedAssets) if (asset.preview_url.startsWith("blob:")) URL.revokeObjectURL(asset.preview_url);
    });
    const blocks = derived(() => composerState.email_blueprint?.blocks ?? []);
    const ownedAssets = run(() => [...assets]);
    const knownImages = derived(() => new Map(ownedAssets.map((asset) => [asset.asset_id, asset])));
    const preview = derived(() => snapshot?.previews?.[previewView]);
    const digestFresh = derived(() => Boolean(preview() && preview().output_sha256 === snapshot?.output_digests?.[`${previewView}_sha256`]));
    const emailPreviewFresh = derived(() => Boolean(snapshot?.previews.email));
    const portalPreviewFresh = derived(() => Boolean(snapshot?.previews.portal));
    const hasSelectedAttachment = derived(() => Boolean(supports_pdf && family.supports_pdf && composerState.document_revision_id !== null));
    const selectedAttachmentFresh = derived(() => !hasSelectedAttachment() || String("") === String(composerState.document_revision_id));
    const checksFresh = derived(() => emailPreviewFresh() && portalPreviewFresh() && selectedAttachmentFresh());
    function ensureBlueprint(state) {
      return {
        ...state,
        email_blueprint: state.email_blueprint ?? { schema_version: "notice-email-1", layout: "flow", blocks: [] }
      };
    }
    function update(next) {
      composerState = next;
    }
    function setBlock(index, next) {
      const changed = blocks().map((block, position) => position === index ? next : block);
      update({
        ...ensureBlueprint(composerState),
        email_blueprint: {
          schema_version: "notice-email-1",
          layout: "flow",
          blocks: changed
        }
      });
    }
    function textRuns(value) {
      const runs = Array.isArray(value) ? value : value && typeof value === "object" && Array.isArray(value.runs) ? value.runs : typeof value === "string" ? [{ text: value }] : value && typeof value === "object" && typeof value.text === "string" ? [value] : [];
      return runs.map((run2) => typeof run2 === "string" ? { text: run2 } : run2 && typeof run2 === "object" ? run2 : { text: "" });
    }
    function setListItemRuns(blockIndex2, itemIndex, runs) {
      const block = blocks()[blockIndex2];
      const items = [...Array.isArray(block.items) ? block.items : []];
      const previous = items[itemIndex];
      if (Array.isArray(previous)) {
        items[itemIndex] = runs;
      } else {
        const item = previous && typeof previous === "object" ? { ...previous, runs } : { runs };
        delete item.text;
        items[itemIndex] = item;
      }
      setBlock(blockIndex2, { ...block, items });
    }
    function setTableCellRuns(blockIndex2, rowIndex, columnIndex, runs) {
      const block = blocks()[blockIndex2];
      const rows = cloneMessageData(Array.isArray(block.rows) ? block.rows : []);
      const row = rows[rowIndex];
      if (!row) return;
      const cells = Array.isArray(row.cells) ? [...row.cells] : [...row];
      const previous = cells[columnIndex];
      if (Array.isArray(previous)) cells[columnIndex] = runs;
      else {
        const cell = previous && typeof previous === "object" ? { ...previous, runs } : { runs };
        delete cell.text;
        cells[columnIndex] = cell;
      }
      rows[rowIndex] = Array.isArray(row.cells) ? { ...row, cells } : cells;
      setBlock(blockIndex2, { ...block, rows });
    }
    function setTableHeaderRuns(blockIndex2, columnIndex, runs) {
      const block = blocks()[blockIndex2];
      const headers = [...Array.isArray(block.headers) ? block.headers : []];
      const previous = headers[columnIndex];
      if (Array.isArray(previous)) headers[columnIndex] = runs;
      else {
        const cell = previous && typeof previous === "object" ? { ...previous, runs } : { runs };
        delete cell.text;
        headers[columnIndex] = cell;
      }
      setBlock(blockIndex2, { ...block, headers });
    }
    function tableCells(row) {
      if (Array.isArray(row)) return row;
      if (row && typeof row === "object" && Array.isArray(row.cells)) return row.cells;
      return [];
    }
    const breadcrumbs = derived(() => [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      {
        label: `${family.domain} notices`,
        href: endpoints.return_url
      },
      { label: family.name }
    ]);
    head("ofg1sv", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(family.name)} · Message composer</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs: breadcrumbs(),
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-5 p-4 pb-28 sm:p-6 sm:pb-28">`);
        PageHeaderBar($$renderer3, {
          title: family.name,
          description: "Write the email and Portal message separately from any document. Publication creates an immutable version for future occurrences only."
        });
        $$renderer3.push(`<!----> <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-3"><p class="text-sm" role="status" aria-live="polite">${escape_html("Ready to compose")}${escape_html("")}</p> `);
        Link($$renderer3, {
          href: endpoints.return_url,
          class: "min-h-11 inline-flex items-center rounded-md px-3 text-sm font-medium text-primary underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to ${escape_html(family.name)}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (copy_source_version) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-md border border-primary/30 bg-primary/5 p-3 text-sm">Version ${escape_html(copy_source_version)} loaded as a new draft source. Save explicitly to create a current draft; the historical version remains unchanged.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (versions.length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<nav class="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3 text-sm" aria-label="Message version history"><span class="font-medium">History</span><!--[-->`);
          const each_array = ensure_array_like(versions);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let version = each_array[$$index];
            $$renderer3.push(`<span class="inline-flex items-center gap-2">`);
            Link($$renderer3, {
              href: version.inspect_url,
              class: "underline underline-offset-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Inspect v${escape_html(version.version)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Link($$renderer3, {
              href: version.copy_state_url,
              class: "underline underline-offset-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Copy v${escape_html(version.version)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></span>`);
          }
          $$renderer3.push(`<!--]--></nav>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div role="group" class="flex gap-2 md:hidden" aria-label="Composer sections"><button class="min-h-11 flex-1 rounded-md border px-3"${attr("aria-pressed", mobileTab === "write")}>Write</button><button class="min-h-11 flex-1 rounded-md border px-3"${attr("aria-pressed", mobileTab === "preview")}>Preview</button></div> <div class="grid min-w-0 gap-5 md:grid-cols-2"><section${attr_class("min-w-0 space-y-5 rounded-xl border border-border bg-card p-4 sm:p-5 md:block", void 0, { "hidden": mobileTab === "preview" })} aria-label="Write message">`);
        if (read_only) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-md border border-muted p-3 text-sm text-muted-foreground">Historical message version — read-only inspection.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <fieldset${attr("disabled", read_only, true)} class="contents"><h2 class="text-lg font-semibold">Write your message</h2> <label class="block space-y-1.5"><span class="text-sm font-medium">Email subject and Portal title</span><input${attr("disabled", read_only, true)} class="min-h-11 w-full rounded-md border border-input bg-background px-3"${attr("value", composerState.subject ?? "")}/></label> <details class="rounded-lg border border-border p-4" open=""><summary class="font-medium cursor-pointer">Portal caption &amp; actions</summary> <div class="mt-4 space-y-3"><label class="block space-y-1.5"><span class="text-sm font-medium">Portal caption</span><textarea${attr("disabled", read_only, true)} class="min-h-24 w-full rounded-md border border-input bg-background p-3" maxlength="500">`);
        const $$body = escape_html(composerState.body ?? "");
        if ($$body) {
          $$renderer3.push(`${$$body}`);
        }
        $$renderer3.push(`</textarea><span class="text-xs text-muted-foreground">Shown in the Portal, separate from the email body.</span></label> <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1.5"><span class="block text-sm font-medium">Shared action label</span><input${attr("disabled", read_only, true)} class="min-h-11 w-full rounded-md border border-input bg-background px-3"${attr("value", composerState.action_label ?? "")}/></label><label class="space-y-1.5"><span class="block text-sm font-medium">Shared action destination</span><input${attr("disabled", read_only, true)} class="min-h-11 w-full rounded-md border border-input bg-background px-3"${attr("value", composerState.action_route ?? "")}/></label></div></div></details> <div class="space-y-3 border-t border-border pt-4"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">Email content</h3><div class="flex flex-wrap gap-2"><button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Add paragraph</button><button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Add heading</button><button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Add list</button><button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Add table</button><button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Add image</button></div></div> `);
        const each_array_1 = ensure_array_like(blocks());
        if (each_array_1.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
            let block = each_array_1[index];
            $$renderer3.push(`<article${attr_class("min-w-0 space-y-2 rounded-lg border border-border p-3", void 0, { "border-primary": blockIndex === index })}><div class="flex items-center justify-between gap-2"><button${attr("disabled", read_only, true)} class="min-h-10 text-left font-medium underline underline-offset-4"${attr("aria-label", `${String(block.type)} block ${index + 1}`)}>${escape_html(index + 1)}. ${escape_html(String(block.type))}</button><button${attr("disabled", read_only, true)} class="min-h-10 px-3 text-sm text-destructive"${attr("aria-label", `Remove block ${index + 1}`)}>Remove</button></div> `);
            if (block.type === "image") {
              $$renderer3.push("<!--[0-->");
              const asset = knownImages().get(String(block.asset_id));
              if (asset) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<img class="max-h-64 max-w-full rounded object-contain"${attr("src", asset.preview_url)}${attr("alt", String(block.alt ?? ""))}/>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<p class="text-sm text-muted-foreground">Owned image preview is unavailable.</p>`);
              }
              $$renderer3.push(`<!--]--> <label class="block space-y-1.5"><span class="text-sm font-medium">Image alt text</span><input${attr("disabled", read_only, true)} class="min-h-11 w-full rounded-md border border-input bg-background px-3"${attr("value", String(block.alt ?? ""))}/></label> <button${attr("disabled", read_only, true)} class="min-h-10 rounded-md border px-3 text-sm">Replace image</button>`);
            } else if (block.type === "divider" || block.type === "spacer") {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<p class="text-sm text-muted-foreground">${escape_html(block.type === "divider" ? "Horizontal divider" : "Space between content")}</p>`);
            } else if (block.type === "paragraph" || block.type === "heading") {
              $$renderer3.push("<!--[2-->");
              if (block.type === "heading") {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<label class="block space-y-1.5"><span class="text-sm font-medium">Heading level</span>`);
                $$renderer3.select(
                  {
                    disabled: read_only,
                    class: "min-h-11 w-full rounded-md border border-input bg-background px-3",
                    value: Number(block.level ?? 2),
                    onchange: (event) => setBlock(index, { ...block, level: Number(event.currentTarget.value) })
                  },
                  ($$renderer4) => {
                    $$renderer4.push(`<!--[-->`);
                    const each_array_2 = ensure_array_like([1, 2, 3, 4, 5, 6]);
                    for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                      let level = each_array_2[$$index_1];
                      $$renderer4.option({ value: level }, ($$renderer5) => {
                        $$renderer5.push(`Heading ${escape_html(level)}`);
                      });
                    }
                    $$renderer4.push(`<!--]-->`);
                  }
                );
                $$renderer3.push(`</label>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              MessageRuns($$renderer3, {
                value: textRuns(block),
                label: `${String(block.type)} block ${index + 1}`,
                tokens,
                scopeKey: `email-block-${index}`,
                readOnly: read_only,
                onChanged: (runs) => setBlock(index, { ...block, runs })
              });
              $$renderer3.push(`<!---->`);
            } else if (block.type === "list") {
              $$renderer3.push("<!--[3-->");
              $$renderer3.push(`<!--[-->`);
              const each_array_3 = ensure_array_like(Array.isArray(block.items) ? block.items : []);
              for (let itemIndex = 0, $$length2 = each_array_3.length; itemIndex < $$length2; itemIndex++) {
                let item = each_array_3[itemIndex];
                $$renderer3.push(`<div class="space-y-2 rounded-lg border border-border p-3">`);
                MessageRuns($$renderer3, {
                  value: textRuns(item),
                  label: `List item ${itemIndex + 1}`,
                  tokens,
                  scopeKey: `list-${index}-${itemIndex}`,
                  readOnly: read_only,
                  onChanged: (runs) => setListItemRuns(index, itemIndex, runs)
                });
                $$renderer3.push(`<!----><button${attr("disabled", read_only, true)} type="button" class="min-h-10 text-sm text-destructive underline"${attr("aria-label", `Remove list item ${itemIndex + 1}`)}>Remove item</button></div>`);
              }
              $$renderer3.push(`<!--]--> <button${attr("disabled", read_only, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Add list item</button>`);
            } else if (block.type === "table") {
              $$renderer3.push("<!--[4-->");
              $$renderer3.push(`<!--[-->`);
              const each_array_4 = ensure_array_like(Array.isArray(block.headers) ? block.headers : []);
              for (let column = 0, $$length2 = each_array_4.length; column < $$length2; column++) {
                let header = each_array_4[column];
                MessageRuns($$renderer3, {
                  value: textRuns(header),
                  label: `Table header ${column + 1}`,
                  tokens,
                  scopeKey: `table-${index}-header-${column}`,
                  readOnly: read_only,
                  onChanged: (runs) => setTableHeaderRuns(index, column, runs)
                });
              }
              $$renderer3.push(`<!--]--> <!--[-->`);
              const each_array_5 = ensure_array_like(Array.isArray(block.rows) ? block.rows : []);
              for (let rowIndex = 0, $$length2 = each_array_5.length; rowIndex < $$length2; rowIndex++) {
                let row = each_array_5[rowIndex];
                $$renderer3.push(`<div class="space-y-3 rounded-lg border border-border p-3"><h4 class="text-sm font-medium">Table row ${escape_html(rowIndex + 1)}</h4><!--[-->`);
                const each_array_6 = ensure_array_like(tableCells(row));
                for (let column = 0, $$length3 = each_array_6.length; column < $$length3; column++) {
                  let cell = each_array_6[column];
                  MessageRuns($$renderer3, {
                    value: textRuns(cell),
                    label: `Table row ${rowIndex + 1}, cell ${column + 1}`,
                    tokens,
                    scopeKey: `table-${index}-${rowIndex}-${column}`,
                    readOnly: read_only,
                    onChanged: (runs) => setTableCellRuns(index, rowIndex, column, runs)
                  });
                }
                $$renderer3.push(`<!--]--><button${attr("disabled", read_only, true)} type="button" class="min-h-10 text-sm text-destructive underline"${attr("aria-label", `Remove table row ${rowIndex + 1}`)}>Remove row</button></div>`);
              }
              $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-2"><button${attr("disabled", read_only, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Add table row</button><button${attr("disabled", read_only, true)} type="button" class="min-h-10 rounded-md border px-3 text-sm">Add table column</button></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></article>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<p class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Start with a heading or paragraph. Your email remains separate from the Portal caption.</p>`);
        }
        $$renderer3.push(`<!--]--> <input${attr("disabled", read_only, true)} class="sr-only" type="file" accept="image/png,image/jpeg" aria-label="Upload a PNG or JPEG image"/> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (supports_pdf && family.supports_pdf) {
          $$renderer3.push("<!--[0-->");
          const selectedDocument = document_versions.find((version) => String(version.id) === String(composerState.document_revision_id));
          $$renderer3.push(`<details class="rounded-lg border border-border p-4" open=""><summary class="font-semibold cursor-pointer">Attachments (eligible family only)</summary> <div class="mt-4 space-y-3"><div><h3 id="message-attachment-heading" class="font-semibold">Attachment</h3><p class="text-sm text-muted-foreground">${escape_html(selectedDocument ? `Attachment: ${family.name} v${selectedDocument.version}` : "No document attached")}</p></div> <p class="text-sm text-muted-foreground">Choose an exact ready version. The attachment stays pinned to that immutable document version.</p> <label class="block space-y-1.5"><span class="text-sm font-medium">Ready document version</span>`);
          $$renderer3.select(
            {
              disabled: read_only || busy || imageBusy,
              class: "min-h-11 w-full rounded-md border border-input bg-background px-3",
              "aria-label": "Attachment document version",
              value: composerState.document_revision_id ?? "",
              onchange: (event) => update({
                ...composerState,
                document_revision_id: event.currentTarget.value ? Number(event.currentTarget.value) : null
              })
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "" }, ($$renderer5) => {
                $$renderer5.push(`No document attached`);
              });
              $$renderer4.push(`<!--[-->`);
              const each_array_7 = ensure_array_like(document_versions);
              for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                let version = each_array_7[$$index_7];
                $$renderer4.option({ value: version.id }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(family.name)} v${escape_html(version.version)}`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(`</label> <div class="flex flex-wrap gap-2"><button type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("disabled", read_only || busy || imageBusy || true, true)}>Check attachment</button> <button type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("disabled", read_only || !selectedDocument?.pdf_url, true)}>Preview document</button> `);
          if (endpoints.document_edit) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<button type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("disabled", read_only || busy || imageBusy, true)}>Edit document</button>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> <p class="text-sm text-muted-foreground" role="status" aria-live="polite">${escape_html(selectedDocument ? "Run Check preview to verify this exact document version." : "No document is pinned; an attachment check is not required.")}</p></div></details>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></fieldset></section> <section${attr_class("min-w-0 space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5 md:block", void 0, { "hidden": mobileTab === "write" })} aria-label="Message preview"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Preview</h2><div class="flex gap-2" aria-label="Preview channel"><button type="button"${attr("aria-pressed", previewView === "email")} class="min-h-11 rounded-md border px-3">Email preview</button><button type="button"${attr("aria-pressed", previewView === "portal")} class="min-h-11 rounded-md border px-3">Portal preview</button></div></div> <fieldset${attr("disabled", read_only, true)} class="contents"><div class="flex flex-wrap items-center gap-2"><button class="min-h-11 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"${attr("disabled", read_only, true)}>Refresh ${escape_html("Email")} preview</button> <p class="text-sm text-muted-foreground" role="status" aria-live="polite">${escape_html(digestFresh() ? "Current server preview" : preview() ? "Stale preview · Check preview required" : "No current preview yet")}</p></div> `);
        if (endpoints.test_mail) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-2 rounded-lg border border-border p-3"><button class="min-h-11 rounded-md border px-4"${attr("disabled", read_only || busy || imageBusy || Boolean(snapshot?.unknown) || true, true)}>${escape_html("Send synthetic test email to verified staff address")}</button><p class="text-sm text-muted-foreground">Uses the server-held verified staff address and synthetic sample context only. Applicant delivery is not available here.</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Email preview viewport width"><span class="text-sm font-medium">Viewport</span><!--[-->`);
          const each_array_8 = ensure_array_like([390, 768, 1024]);
          for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
            let width = each_array_8[$$index_8];
            $$renderer3.push(`<button class="min-h-10 rounded-md border px-3 text-sm"${attr("aria-pressed", previewWidth === width)}>${escape_html(width)}px</button>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (preview() && digestFresh()) {
          $$renderer3.push("<!--[0-->");
          if (preview().email) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="max-w-full overflow-x-auto"${attr("aria-label", `Email preview at ${previewWidth}px CSS viewport`)}><iframe title="Current production Email preview"${attr("width", previewWidth)} sandbox="" referrerpolicy="no-referrer" class="h-[32rem] rounded-lg border bg-white"${attr_style(`width:${previewWidth}px;max-width:none`)}${attr("srcdoc", noticePreviewDocument(preview().email.preview_html))}></iframe></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="flex min-h-64 items-center justify-center rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Save your message and request a server-rendered ${escape_html("Email")} preview to see current output.</div>`);
        }
        $$renderer3.push(`<!--]--></fieldset></section></div> `);
        if (!read_only) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<footer data-notice-message-action-footer="" class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-background/95 p-3 backdrop-blur sm:px-6"><div class="mx-auto flex max-w-7xl items-center justify-between gap-2"><div class="flex shrink-0 items-center gap-2"><button class="min-h-11 rounded-md border px-3 text-sm sm:px-4"${attr("disabled", Boolean(snapshot?.unknown), true)}>Save draft</button> `);
          {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> `);
          if (checksFresh() || snapshot?.unknown_operation === "publish") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<button class="min-h-11 flex-1 rounded-md bg-primary px-3 text-sm text-primary-foreground sm:flex-none sm:px-4"${attr("disabled", true, true)}>${escape_html("Review & publish")}</button>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<button class="min-h-11 flex-1 rounded-md bg-primary px-3 text-sm text-primary-foreground sm:flex-none sm:px-4"${attr("disabled", Boolean(snapshot?.unknown), true)}>${escape_html("Check preview")}</button>`);
          }
          $$renderer3.push(`<!--]--></div></footer>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
    });
  });
}
export {
  Message as default
};
