import "clsx";
import { g as sanitize_props, i as spread_props, j as slot, l as bind_props, e as escape_html, a as ensure_array_like, b as attr, s as stringify, B as Button, d as derived, n as newIdempotencyKey, m as csrfHeaders } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { X } from "./x-CnxrMbMn.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
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
function Arrow_down($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 5v14" }],
    ["path", { "d": "m19 12-7 7-7-7" }]
  ];
  Icon($$renderer, spread_props([
    { name: "arrow-down" },
    $$sanitized_props,
    {
      /**
       * @component @name ArrowDown
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgNXYxNCIgLz4KICA8cGF0aCBkPSJtMTkgMTItNyA3LTctNyIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/arrow-down
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Arrow_up($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "m5 12 7-7 7 7" }],
    ["path", { "d": "M12 19V5" }]
  ];
  Icon($$renderer, spread_props([
    { name: "arrow-up" },
    $$sanitized_props,
    {
      /**
       * @component @name ArrowUp
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJtNSAxMiA3LTcgNyA3IiAvPgogIDxwYXRoIGQ9Ik0xMiAxOVY1IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/arrow-up
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Image_plus($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 5h6" }],
    ["path", { "d": "M19 2v6" }],
    [
      "path",
      {
        "d": "M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5"
      }
    ],
    ["path", { "d": "m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" }],
    ["circle", { "cx": "9", "cy": "9", "r": "2" }]
  ];
  Icon($$renderer, spread_props([
    { name: "image-plus" },
    $$sanitized_props,
    {
      /**
       * @component @name ImagePlus
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgNWg2IiAvPgogIDxwYXRoIGQ9Ik0xOSAydjYiIC8+CiAgPHBhdGggZD0iTTIxIDExLjVWMTlhMiAyIDAgMCAxLTIgMkg1YTIgMiAwIDAgMS0yLTJWNWEyIDIgMCAwIDEgMi0yaDcuNSIgLz4KICA8cGF0aCBkPSJtMjEgMTUtMy4wODYtMy4wODZhMiAyIDAgMCAwLTIuODI4IDBMNiAyMSIgLz4KICA8Y2lyY2xlIGN4PSI5IiBjeT0iOSIgcj0iMiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/image-plus
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function ProgramProfileEditor($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      program,
      profile = void 0,
      onsave,
      onpreview,
      onaddphotos,
      onremovephoto,
      onpublish,
      revision,
      publishedRevision,
      canPublish,
      isSaving = false,
      isPublishing = false,
      serverErrors = {},
      statusMessage = ""
    } = $$props;
    const maxPhotos = 6;
    const maxFileBytes = 5 * 1024 * 1024;
    const allowedTypes = /* @__PURE__ */ new Set(["image/jpeg", "image/png", "image/webp"]);
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Edit public program profile" }
    ];
    let pendingAdding = false;
    let addError = "";
    let actionAttempted = false;
    let baselineProfile = JSON.stringify(profile);
    const isDirty = derived(() => JSON.stringify(profile) !== baselineProfile);
    const shortDescriptionLength = derived(() => String(profile.short_description ?? "").length);
    const descriptionLength = derived(() => String(profile.description ?? "").length);
    const fieldErrors = derived(() => {
      const errors = { photos: {} };
      if (shortDescriptionLength() > 240) {
        errors.short_description = "Keep the short description within 240 characters.";
      } else if (serverErrors.short_description) {
        errors.short_description = serverErrors.short_description;
      }
      if (!String(profile.description ?? "").trim()) {
        errors.description = "Add a full program description before saving or previewing.";
      } else if (descriptionLength() > 1e4) {
        errors.description = "Keep the full description within 10,000 characters.";
      } else if (serverErrors.description) {
        errors.description = serverErrors.description;
      }
      for (const [index, photo] of (profile.photos ?? []).entries()) {
        const photoErrors = {};
        if (!String(photo.alt_text ?? "").trim()) {
          photoErrors.alt = "Add meaningful alternative text for this photo.";
        } else if (String(photo.alt_text).length > 160) {
          photoErrors.alt = "Alternative text must be 160 characters or fewer.";
        } else if (serverErrors[`photos.${index}.alt_text`]) {
          photoErrors.alt = serverErrors[`photos.${index}.alt_text`];
        }
        if (String(photo.caption ?? "").length > 240) {
          photoErrors.caption = "Captions must be 240 characters or fewer.";
        } else if (serverErrors[`photos.${index}.caption`]) {
          photoErrors.caption = serverErrors[`photos.${index}.caption`];
        }
        if (photo.available === false) {
          photoErrors.media = "This private photo is unavailable. Remove or replace it before saving.";
        } else if (serverErrors[`photos.${index}.id`]) {
          photoErrors.media = serverErrors[`photos.${index}.id`];
        }
        if (Object.keys(photoErrors).length > 0) {
          errors.photos[photo.id] = photoErrors;
        }
      }
      if (serverErrors.photos) {
        errors.photos._general = { media: serverErrors.photos };
      }
      return errors;
    });
    const hasValidationErrors = derived(() => Boolean(fieldErrors().short_description || fieldErrors().description || Object.keys(fieldErrors().photos).length > 0 || Object.keys(serverErrors).length > 0 || (profile.photos ?? []).length > maxPhotos));
    const canUseDraft = derived(() => !pendingAdding && !isSaving && !isPublishing && !hasValidationErrors());
    const canPreviewSaved = derived(() => canUseDraft() && !isDirty());
    const previewPhoto = derived(() => profile.photos?.[0] ?? null);
    const commandPending = derived(() => isSaving || isPublishing);
    function updateProfile(key, value) {
      if (commandPending()) return;
      serverErrors = {};
      profile = { ...profile, [key]: value };
    }
    function clearServerErrors() {
      serverErrors = {};
    }
    function updatePhoto(id, key, value) {
      updateProfile("photos", profile.photos.map((photo) => photo.id === id ? { ...photo, [key]: value } : photo));
    }
    function movePhoto(index, offset) {
      const destination = index + offset;
      if (destination < 0 || destination >= profile.photos.length) return;
      const photos = [...profile.photos];
      [photos[index], photos[destination]] = [photos[destination], photos[index]];
      updateProfile("photos", photos);
    }
    function movePhotoToCover(index) {
      if (index <= 0 || index >= profile.photos.length) return;
      const photos = [...profile.photos];
      photos.unshift(...photos.splice(index, 1));
      updateProfile("photos", photos);
    }
    function validateSelectedFiles(files) {
      if (profile.photos.length + files.length > maxPhotos) {
        return `Choose no more than ${maxPhotos - profile.photos.length} additional photo${maxPhotos - profile.photos.length === 1 ? "" : "s"}.`;
      }
      for (const file of Array.from(files)) {
        if (!allowedTypes.has(file.type)) {
          return `${file.name} is not a supported image. Choose JPEG, PNG, or WebP.`;
        }
        if (file.size > maxFileBytes) {
          return `${file.name} is larger than 5 MiB. Choose a smaller image.`;
        }
      }
      return null;
    }
    async function addPhotos(event) {
      const input = event.currentTarget;
      if (!(input instanceof HTMLInputElement) || !input.files?.length) {
        return;
      }
      const files = input.files;
      addError = validateSelectedFiles(files) ?? "";
      if (addError) {
        input.value = "";
        return;
      }
      pendingAdding = true;
      try {
        const result = await onaddphotos(files);
        addError = result.error ?? "";
        if (!addError) {
          serverErrors = {};
        }
      } catch {
        addError = "The photo could not be uploaded. Choose it again to retry safely.";
      } finally {
        pendingAdding = false;
        input.value = "";
      }
    }
    function removePhoto(id) {
      serverErrors = {};
      onremovephoto(id);
    }
    function saveDraft() {
      actionAttempted = true;
      if (!canUseDraft()) return;
      onsave();
    }
    function previewProgram() {
      actionAttempted = true;
      if (!canPreviewSaved()) return;
      onpreview();
    }
    function publishProfile() {
      actionAttempted = true;
      if (!canUseDraft() || isDirty() || !canPublish) return;
      onpublish();
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Edit public program profile",
            description: "Update the saved description and photo gallery shown in a published program profile."
          });
          $$renderer4.push(`<!----> <p class="border-border bg-card/70 text-muted-foreground rounded-lg border p-3 text-sm" role="status" data-testid="profile-revision-status">`);
          if (revision > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`Saved revision ${escape_html(revision)}.`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`No saved revision yet.`);
          }
          $$renderer4.push(`<!--]--> `);
          if (publishedRevision) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`Guests currently see published revision ${escape_html(publishedRevision)}.`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`No profile has been published yet.`);
          }
          $$renderer4.push(`<!--]--> `);
          if (isDirty()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`Save the draft before opening a private preview or publishing.`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`This editor is private; visitors never see a draft.`);
          }
          $$renderer4.push(`<!--]--></p> `);
          if (statusMessage) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="border-border bg-card/70 text-foreground rounded-lg border p-3 text-sm" role="status" data-testid="profile-status">${escape_html(statusMessage)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <form class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.85fr)]" novalidate=""><div class="min-w-0 space-y-6"><section class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6" aria-labelledby="program-identity-title"><div><h2 id="program-identity-title" class="text-lg font-semibold">Program identity</h2> <p class="text-muted-foreground mt-1 text-sm">Catalog details are read-only here.</p></div> <div class="grid gap-4 sm:grid-cols-[minmax(8rem,0.45fr)_minmax(0,1fr)]"><div class="space-y-2">`);
          Label($$renderer4, {
            for: "profile-program-code",
            class: "",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Program code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "profile-program-code",
            type: "text",
            value: program.code,
            readonly: true,
            class: "bg-muted/40 min-h-11"
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "profile-program-name",
            class: "",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Program name`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "profile-program-name",
            type: "text",
            value: program.name,
            readonly: true,
            class: "bg-muted/40 min-h-11"
          });
          $$renderer4.push(`<!----></div></div></section> <section class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6" aria-labelledby="profile-description-title"><div><h2 id="profile-description-title" class="text-lg font-semibold">Program description</h2> <p class="text-muted-foreground mt-1 text-sm">Use clear, welcoming language that helps prospective
                            students understand the program.</p></div> <div class="space-y-2"><div class="flex flex-wrap items-center justify-between gap-2">`);
          Label($$renderer4, {
            for: "short-description",
            class: "",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Short description`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <span class="text-muted-foreground text-xs tabular-nums" aria-live="polite">${escape_html(shortDescriptionLength())}/240</span></div> `);
          Textarea($$renderer4, {
            id: "short-description",
            disabled: commandPending(),
            oninput: clearServerErrors,
            rows: "3",
            maxlength: "240",
            "aria-invalid": Boolean(fieldErrors().short_description),
            "aria-describedby": "short-description-help short-description-error",
            class: "min-h-24 resize-y leading-6",
            "data-testid": "short-description",
            get value() {
              return profile.short_description;
            },
            set value($$value) {
              profile.short_description = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p id="short-description-help" class="text-muted-foreground text-xs">A concise introduction shown near the program title.</p> `);
          if (fieldErrors().short_description) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p id="short-description-error" class="text-destructive text-sm" role="alert">${escape_html(fieldErrors().short_description)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-2"><div class="flex flex-wrap items-center justify-between gap-2">`);
          Label($$renderer4, {
            for: "full-description",
            class: "",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Full description`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <span class="text-muted-foreground text-xs tabular-nums" aria-live="polite">${escape_html(descriptionLength().toLocaleString())}/10,000</span></div> `);
          Textarea($$renderer4, {
            id: "full-description",
            disabled: commandPending(),
            oninput: clearServerErrors,
            rows: "9",
            maxlength: "10000",
            "aria-invalid": Boolean(fieldErrors().description),
            "aria-describedby": "full-description-help full-description-error",
            class: "min-h-48 resize-y leading-6",
            "data-testid": "full-description",
            get value() {
              return profile.description;
            },
            set value($$value) {
              profile.description = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> <p id="full-description-help" class="text-muted-foreground text-xs">Describe the learning experience, areas of study,
                            and opportunities without making unverified
                            promises.</p> `);
          if (fieldErrors().description) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p id="full-description-error" class="text-destructive text-sm" role="alert">${escape_html(fieldErrors().description)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div></section> <section class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6" aria-labelledby="profile-photos-title"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 id="profile-photos-title" class="text-lg font-semibold">Program photos</h2> <p class="text-muted-foreground mt-1 max-w-2xl text-sm leading-6">Stage up to six JPEG, PNG, or WebP images, each no larger than 5 MiB and 4096 by 4096 pixels. The server strips source metadata and keeps sanitized photos private until a profile revision is published.</p></div> <span class="bg-muted text-muted-foreground shrink-0 rounded-md px-2.5 py-1.5 text-xs font-medium tabular-nums">${escape_html(profile.photos.length)}/6 photos</span></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "program-photo-files",
            class: "",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Choose photos`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "program-photo-files",
            type: "file",
            accept: "image/jpeg,image/png,image/webp",
            multiple: true,
            disabled: commandPending() || pendingAdding || profile.photos.length >= maxPhotos,
            onchange: addPhotos,
            "aria-describedby": "photo-upload-help photo-add-error",
            "data-testid": "photo-file-input",
            class: "min-h-11 py-1.5"
          });
          $$renderer4.push(`<!----> <p id="photo-upload-help" class="text-muted-foreground text-xs leading-5">Supported formats: JPEG, PNG, WebP. Maximum 5 MiB and 4096 by 4096 pixels per image; six photos in this profile.</p> `);
          if (pendingAdding) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground text-sm" role="status" data-testid="photo-pending">Uploading and sanitizing selected photos…</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (addError) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p id="photo-add-error" class="text-destructive text-sm" role="alert" data-testid="photo-add-error">${escape_html(addError)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (profile.photos.length > maxPhotos) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">This preview can show no more than 6 photos.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (profile.photos.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border bg-muted/20 grid min-h-40 place-items-center rounded-lg border border-dashed px-5 py-8 text-center" data-testid="photo-empty-state"><div class="space-y-2">`);
            Image_plus($$renderer4, {
              class: "text-muted-foreground mx-auto size-7",
              "aria-hidden": "true"
            });
            $$renderer4.push(`<!----> <p class="text-foreground font-medium">No program photos added</p> <p class="text-muted-foreground text-sm">Choose an image above to start the gallery.</p></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<ol class="space-y-4" aria-label="Program photo order" data-testid="photo-gallery-editor"><!--[-->`);
            const each_array = ensure_array_like(profile.photos);
            for (let index = 0, $$length = each_array.length; index < $$length; index++) {
              let photo = each_array[index];
              $$renderer4.push(`<li class="border-border/80 bg-background/50 min-w-0 rounded-lg border p-3 sm:p-4"${attr("data-testid", `photo-item-${stringify(photo.id)}`)}><div class="grid min-w-0 gap-4 sm:grid-cols-[minmax(8rem,0.72fr)_minmax(0,1.5fr)]"><div class="bg-muted relative aspect-[4/3] overflow-hidden rounded-md">`);
              if (photo.available === false) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="text-muted-foreground grid size-full place-items-center p-3 text-center text-xs" role="note">This private photo is unavailable. Remove or replace it before saving.</div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<img${attr("src", photo.src)}${attr("alt", photo.alt_text || "Photo awaiting alternative text")} class="size-full object-cover"/>`);
              }
              $$renderer4.push(`<!--]--> `);
              if (index === 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<span class="bg-background/95 text-foreground absolute bottom-2 left-2 rounded-md px-2 py-1 text-xs font-semibold shadow-sm" data-testid="cover-badge">Cover photo</span>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> <div class="min-w-0 space-y-3"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-foreground text-sm font-semibold">Photo ${escape_html(index + 1)}</p> <div class="flex flex-wrap gap-2"${attr("aria-label", `Photo ${stringify(index + 1)} actions`)}>`);
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                size: "sm",
                class: "min-h-11 min-w-11 px-2.5",
                disabled: commandPending() || index === 0,
                onclick: () => movePhoto(index, -1),
                "aria-label": `Move photo ${stringify(index + 1)} up`,
                "data-testid": `photo-move-up-${stringify(photo.id)}`,
                children: ($$renderer5) => {
                  Arrow_up($$renderer5, { class: "size-4", "aria-hidden": "true" });
                  $$renderer5.push(`<!----><span class="sr-only">Move up</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                size: "sm",
                class: "min-h-11 min-w-11 px-2.5",
                disabled: commandPending() || index === profile.photos.length - 1,
                onclick: () => movePhoto(index, 1),
                "aria-label": `Move photo ${stringify(index + 1)} down`,
                "data-testid": `photo-move-down-${stringify(photo.id)}`,
                children: ($$renderer5) => {
                  Arrow_down($$renderer5, { class: "size-4", "aria-hidden": "true" });
                  $$renderer5.push(`<!----><span class="sr-only">Move down</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (index > 0) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  type: "button",
                  variant: "outline",
                  size: "sm",
                  class: "min-h-11",
                  disabled: commandPending(),
                  onclick: () => movePhotoToCover(index),
                  "data-testid": `photo-make-cover-${stringify(photo.id)}`,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Make cover`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                size: "sm",
                class: "text-destructive min-h-11 min-w-11 px-2.5",
                disabled: commandPending(),
                onclick: () => removePhoto(photo.id),
                "aria-label": `Remove photo ${stringify(index + 1)}`,
                "data-testid": `photo-remove-${stringify(photo.id)}`,
                children: ($$renderer5) => {
                  X($$renderer5, { class: "size-4", "aria-hidden": "true" });
                  $$renderer5.push(`<!----><span class="sr-only">Remove</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div></div> <div class="space-y-2"><div class="flex items-center justify-between gap-2">`);
              Label($$renderer4, {
                for: `photo-alt-${stringify(photo.id)}`,
                class: "",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Alternative text`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> <span class="text-muted-foreground text-xs tabular-nums">${escape_html(photo.alt_text.length)}/160</span></div> `);
              Input($$renderer4, {
                id: `photo-alt-${stringify(photo.id)}`,
                type: "text",
                disabled: commandPending(),
                value: photo.alt_text,
                maxlength: "160",
                oninput: (event) => {
                  if (event.currentTarget instanceof HTMLInputElement) {
                    updatePhoto(photo.id, "alt_text", event.currentTarget.value);
                  }
                },
                "aria-invalid": Boolean(fieldErrors().photos[photo.id]?.alt),
                "aria-describedby": `photo-alt-error-${stringify(photo.id)}`,
                class: "min-h-11",
                "data-testid": `photo-alt-${stringify(photo.id)}`
              });
              $$renderer4.push(`<!----> `);
              if (fieldErrors().photos[photo.id]?.alt) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p${attr("id", `photo-alt-error-${stringify(photo.id)}`)} class="text-destructive text-sm" role="alert">${escape_html(fieldErrors().photos[photo.id]?.alt)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> <div class="space-y-2"><div class="flex items-center justify-between gap-2">`);
              Label($$renderer4, {
                for: `photo-caption-${stringify(photo.id)}`,
                class: "",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Caption <span class="text-muted-foreground font-normal">(optional)</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> <span class="text-muted-foreground text-xs tabular-nums">${escape_html(photo.caption.length)}/240</span></div> `);
              Input($$renderer4, {
                id: `photo-caption-${stringify(photo.id)}`,
                type: "text",
                disabled: commandPending(),
                value: photo.caption,
                maxlength: "240",
                oninput: (event) => {
                  if (event.currentTarget instanceof HTMLInputElement) {
                    updatePhoto(photo.id, "caption", event.currentTarget.value);
                  }
                },
                "aria-invalid": Boolean(fieldErrors().photos[photo.id]?.caption),
                "aria-describedby": `photo-caption-error-${stringify(photo.id)}`,
                class: "min-h-11",
                "data-testid": `photo-caption-${stringify(photo.id)}`
              });
              $$renderer4.push(`<!----> `);
              if (fieldErrors().photos[photo.id]?.caption) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p${attr("id", `photo-caption-error-${stringify(photo.id)}`)} class="text-destructive text-sm" role="alert">${escape_html(fieldErrors().photos[photo.id]?.caption)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div> `);
              if (fieldErrors().photos[photo.id]?.media) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(fieldErrors().photos[photo.id]?.media)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div></div></li>`);
            }
            $$renderer4.push(`<!--]--></ol>`);
          }
          $$renderer4.push(`<!--]--></section></div> <aside class="min-w-0 space-y-5 xl:sticky xl:top-6 xl:self-start" aria-label="Program profile actions and preview"><section class="border-border bg-card/80 min-w-0 space-y-4 rounded-lg border p-4 sm:p-5" aria-labelledby="program-preview-title"><div><h2 id="program-preview-title" class="text-lg font-semibold">Private editor preview</h2> <p class="text-muted-foreground mt-1 text-sm">This preview follows your current editor state. Guests see only the last explicitly published revision.</p></div> <article class="border-border bg-background min-w-0 overflow-hidden rounded-lg border">`);
          if (previewPhoto()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="bg-muted aspect-[4/3]">`);
            if (previewPhoto().available === false) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="text-muted-foreground grid size-full place-items-center p-4 text-center text-sm">Private cover photo unavailable. Remove or replace it before publishing.</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<img${attr("src", previewPhoto().src)}${attr("alt", previewPhoto().alt_text || "Program cover photo preview")} class="size-full object-cover" data-testid="profile-preview-image"/>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="bg-muted/40 text-muted-foreground grid aspect-[4/3] place-items-center px-4 text-center text-sm" data-testid="profile-preview-image-empty">No cover photo selected</div>`);
          }
          $$renderer4.push(`<!--]--> <div class="space-y-3 p-4"><p class="text-muted-foreground text-xs font-semibold tracking-wide">${escape_html(program.code)}</p> <h3 class="text-foreground text-xl leading-snug font-semibold">${escape_html(program.name)}</h3> <p class="text-foreground text-sm leading-6">${escape_html(profile.short_description.trim() || "Your short description will appear here.")}</p> `);
          if (previewPhoto()?.caption) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground text-xs leading-5">${escape_html(previewPhoto().caption)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <p class="text-muted-foreground line-clamp-4 text-sm leading-6 whitespace-pre-line">${escape_html(profile.description.trim() || "Your full program description will appear here.")}</p> <p class="border-border text-muted-foreground border-t pt-3 text-xs">${escape_html(profile.photos.length)}
                                ${escape_html(profile.photos.length === 1 ? "photo" : "photos")} in this gallery</p></div></article> `);
          if ((actionAttempted || Object.keys(fieldErrors().photos).length > 0 || !String(profile.description ?? "").trim()) && hasValidationErrors()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-destructive/30 bg-destructive/5 text-destructive rounded-md border p-3 text-sm" role="alert" data-testid="profile-validation-summary"><p class="font-medium">Review the profile before saving or previewing.</p> `);
            if (serverErrors.expected_revision) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-1">${escape_html(serverErrors.expected_revision)} Your edits are preserved. Reload the current draft version before trying again.</p> `);
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                class: "mt-2 min-h-11",
                disabled: commandPending(),
                onclick: () => window.location.reload(),
                "data-testid": "reload-latest-profile",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Load latest profile revision`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (serverErrors.photos) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-1">${escape_html(serverErrors.photos)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (fieldErrors().description) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-1">${escape_html(fieldErrors().description)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (Object.keys(fieldErrors().photos).length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-1">Add valid alternative text and keep each
                                    caption within its limit.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (fieldErrors().short_description) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-1">${escape_html(fieldErrors().short_description)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></section> <section class="border-border bg-card/80 space-y-4 rounded-lg border p-4 sm:p-5" aria-labelledby="profile-actions-title"><div><h2 id="profile-actions-title" class="text-lg font-semibold">Save and publish</h2> <p class="text-muted-foreground mt-1 text-sm leading-5">Save a private draft first. Publishing explicitly makes only that saved revision visible to visitors.</p></div> <div class="grid gap-2">`);
          Button($$renderer4, {
            type: "button",
            class: "min-h-11 w-full",
            disabled: !canUseDraft(),
            onclick: saveDraft,
            "data-testid": "save-program-draft",
            children: ($$renderer5) => {
              if (isSaving) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`Saving draft…`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.push(`Save draft`);
              }
              $$renderer5.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "button",
            variant: "outline",
            class: "min-h-11 w-full",
            disabled: !canPreviewSaved(),
            onclick: previewProgram,
            "data-testid": "preview-program",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Preview saved draft privately`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            type: "button",
            class: "min-h-11 w-full",
            disabled: !canPublish || !canUseDraft() || isDirty() || revision < 1 || publishedRevision === revision,
            onclick: publishProfile,
            "aria-describedby": "publish-program-guidance",
            "data-testid": "publish-program",
            children: ($$renderer5) => {
              if (isPublishing) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`Publishing saved revision…`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.push(`Publish saved draft revision`);
              }
              $$renderer5.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <p id="publish-program-guidance" class="text-muted-foreground text-xs leading-5">`);
          if (isDirty()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`Save the current edits as a draft before publishing. Unsaved text and photo changes are never published.`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`Publishing makes only this saved revision public.`);
          }
          $$renderer4.push(`<!--]--></p></div></section></aside></form></div>`);
        }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { profile, serverErrors });
  });
}
function ProgramPublicDetails($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      program,
      profile: initialProfile,
      revision: initialRevision,
      published_revision: initialPublishedRevision,
      canPublish: initialCanPublish = false
    } = $$props;
    let profile = initialProfile;
    let revision = Number(initialRevision ?? 0);
    let publishedRevision = initialPublishedRevision ?? null;
    let canPublish = Boolean(initialCanPublish);
    let statusMessage = "";
    let serverErrors = {};
    let isSaving = false;
    let isPublishing = false;
    let pendingCommand = null;
    const pendingPhotoRequests = /* @__PURE__ */ new Map();
    const editorUrl = () => `/setup/programs/${Number(program.id)}/public-details`;
    const previewUrl = () => `${editorUrl()}/preview`;
    const uploadUrl = () => `${editorUrl()}/photos`;
    function requestHeaders() {
      return {
        ...csrfHeaders(),
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      };
    }
    function normalizeErrors(errors = {}) {
      return Object.fromEntries(Object.entries(errors).map(([key, value]) => [
        key,
        Array.isArray(value) ? String(value[0] ?? "") : String(value ?? "")
      ]));
    }
    function commandRequestId(operation, data) {
      const signature = JSON.stringify({ operation, revision, data });
      if (pendingCommand?.signature !== signature) {
        pendingCommand = { signature, requestId: newIdempotencyKey() };
      }
      return pendingCommand.requestId;
    }
    async function submitCommand(operation) {
      const photoSnapshot = profile.photos.map((photo) => ({
        id: Number(photo.id),
        alt_text: photo.alt_text,
        caption: photo.caption ?? ""
      }));
      const data = operation === "save" ? {
        expected_revision: revision,
        short_description: profile.short_description,
        description: profile.description,
        photos: photoSnapshot
      } : { expected_revision: revision };
      const payload = { ...data, request_id: commandRequestId(operation, data) };
      serverErrors = {};
      statusMessage = "";
      isSaving = operation === "save";
      isPublishing = operation === "publish";
      try {
        const response = await fetch(operation === "save" ? editorUrl() : `${editorUrl()}/publish`, {
          method: operation === "save" ? "PUT" : "POST",
          credentials: "same-origin",
          headers: requestHeaders(),
          body: JSON.stringify(payload)
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          serverErrors = normalizeErrors(body.errors ?? {});
          statusMessage = response.status === 403 ? "Your program-profile authority changed. Refresh this page and contact your administrator if access should remain." : body.message ?? "The profile command was not saved. Your current edits are still here.";
          return;
        }
        const saved = body.profile;
        profile = {
          short_description: saved.short_description,
          description: saved.description,
          photos: saved.photos ?? [],
          revision: Number(saved.revision),
          is_published: Boolean(saved.is_published)
        };
        revision = Number(saved.revision);
        if (operation === "publish") {
          publishedRevision = revision;
          statusMessage = `Revision ${revision} is now published. Guests see this saved version.`;
        } else {
          statusMessage = `Draft revision ${revision} saved privately. Guests still see the last published version.`;
        }
        pendingCommand = null;
      } catch {
        statusMessage = "The server could not be reached. Your edits remain here; retrying the same command will reuse its request ID.";
      } finally {
        isSaving = false;
        isPublishing = false;
      }
    }
    function removePhoto(id) {
      profile = {
        ...profile,
        photos: profile.photos.filter((photo) => String(photo.id) !== String(id))
      };
    }
    function fileRetryKey(file) {
      return `${file.name}\0${file.size}\0${file.lastModified}`;
    }
    async function addPhotos(files) {
      for (const file of Array.from(files)) {
        const signature = fileRetryKey(file);
        const requestId = pendingPhotoRequests.get(signature) ?? newIdempotencyKey();
        pendingPhotoRequests.set(signature, requestId);
        const form = new FormData();
        form.append("request_id", requestId);
        form.append("photo", file);
        try {
          const response = await fetch(uploadUrl(), {
            method: "POST",
            credentials: "same-origin",
            headers: {
              ...csrfHeaders(),
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: form
          });
          const body = await response.json().catch(() => ({}));
          if (!response.ok) {
            const errors = normalizeErrors(body.errors ?? {});
            if (errors.request_id) {
              pendingPhotoRequests.delete(signature);
            }
            return {
              error: errors.photo ?? errors.request_id ?? body.message ?? "The photo could not be added. Choose it again to retry."
            };
          }
          profile = { ...profile, photos: [...profile.photos, body.photo] };
          pendingPhotoRequests.delete(signature);
        } catch {
          return {
            error: "The server could not be reached. The same upload file can be selected again to retry safely."
          };
        }
      }
      return {};
    }
    function openPrivatePreview() {
      router.visit(previewUrl(), { preserveScroll: true });
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      ProgramProfileEditor($$renderer3, {
        program,
        revision,
        publishedRevision,
        canPublish,
        isSaving,
        isPublishing,
        statusMessage,
        onsave: () => submitCommand("save"),
        onpreview: openPrivatePreview,
        onaddphotos: addPhotos,
        onremovephoto: removePhoto,
        onpublish: () => submitCommand("publish"),
        get profile() {
          return profile;
        },
        set profile($$value) {
          profile = $$value;
          $$settled = false;
        },
        get serverErrors() {
          return serverErrors;
        },
        set serverErrors($$value) {
          serverErrors = $$value;
          $$settled = false;
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
  ProgramPublicDetails as default
};
