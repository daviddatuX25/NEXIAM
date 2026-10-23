import { c as attr_class, b as attr, e as escape_html, l as bind_props } from "../app.js";
function File_upload($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      FileIcon = null,
      icon = "upload",
      label = "Upload file",
      sublabel = "Drag and drop or click to browse",
      accept = "*",
      multiple = false,
      maxSize = null,
      maxFiles = null,
      disabled = false,
      files = [],
      error = "",
      onfiles = null,
      onerror = null
    } = $$props;
    let isDragging = false;
    $$renderer2.push(`<label${attr_class("file-upload svelte-1773h6p", void 0, {
      "dragging": isDragging,
      "disabled": disabled,
      "has-error": !!error
    })}${attr("tabindex", disabled ? -1 : 0)}><input type="file"${attr("accept", accept)}${attr("multiple", multiple, true)}${attr("disabled", disabled, true)} class="sr-only"/> <div class="file-upload-content"><div class="icon-wrapper svelte-1773h6p">`);
    if (icon === "upload") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>`);
    } else if (FileIcon) {
      $$renderer2.push("<!--[1-->");
      if (FileIcon) {
        $$renderer2.push("<!--[-->");
        FileIcon($$renderer2, { class: "size-5" });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="text-content svelte-1773h6p"><span class="label svelte-1773h6p">${escape_html(label)}</span> `);
    if (sublabel) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="sublabel svelte-1773h6p">${escape_html(sublabel)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (accept !== "*") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="accept-hint svelte-1773h6p">Accepted: ${escape_html(accept)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (maxSize) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="size-hint svelte-1773h6p">Max: ${escape_html(maxSize)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (error) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="error-message svelte-1773h6p">${escape_html(error)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></label>`);
    bind_props($$props, { files, error });
  });
}
export {
  File_upload as F
};
