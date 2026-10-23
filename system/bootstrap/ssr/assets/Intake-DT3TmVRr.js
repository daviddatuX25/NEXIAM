import { e as escape_html, B as Button, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
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
function Intake($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const page = usePage();
    const saveError = derived(() => page.props.errors?.omr_bundle ?? "");
    const status = derived(() => page.props.flash?.status ?? "");
    let templateCode = "";
    let templateFile = null;
    let configFile = null;
    let manifestFile = null;
    let referenceFiles = [];
    let saving = false;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      StaffLayout($$renderer3, {
        children: ($$renderer4) => {
          PageHeaderBar($$renderer4, {
            title: "Register Native Bundle",
            description: "Upload a verified OMRChecker package containing template definitions, configuration, reference assets, and manifest."
          });
          $$renderer4.push(`<!----> <div class="min-w-0 w-full max-w-5xl space-y-6 p-4 sm:p-6">`);
          if (status()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded bg-green-50 p-3 text-sm text-green-800">${escape_html(status())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (saveError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded bg-red-50 p-3 text-sm text-red-800">${escape_html(saveError())}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] items-start"><div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h2 class="text-sm font-semibold text-foreground">Package Files</h2> <p class="text-xs text-muted-foreground">Select the verified OMRChecker asset bundle files</p></div> <form method="post" action="/setup/omr/bundles" enctype="multipart/form-data" class="space-y-5"><div class="space-y-2">`);
          Label($$renderer4, {
            for: "template_code",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Template code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "template_code",
            name: "template_code",
            placeholder: "e.g. REAL-OMR-2026",
            required: true,
            get value() {
              return templateCode;
            },
            set value($$value) {
              templateCode = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "template",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->template.json`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "template",
            name: "template",
            type: "file",
            accept: ".json",
            required: true,
            get files() {
              return templateFile;
            },
            set files($$value) {
              templateFile = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "config",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->config.json`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "config",
            name: "config",
            type: "file",
            accept: ".json",
            required: true,
            get files() {
              return configFile;
            },
            set files($$value) {
              configFile = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "manifest",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->manifest.json`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "manifest",
            name: "manifest",
            type: "file",
            accept: ".json",
            required: true,
            get files() {
              return manifestFile;
            },
            set files($$value) {
              manifestFile = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-2">`);
          Label($$renderer4, {
            for: "references",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Reference assets (one SVG per page)`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "references",
            name: "references[]",
            type: "file",
            accept: ".svg",
            multiple: true,
            required: true,
            get files() {
              return referenceFiles;
            },
            set files($$value) {
              referenceFiles = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="flex gap-3">`);
          Button($$renderer4, {
            type: "submit",
            disabled: saving,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Register bundle`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            asChild: true,
            variant: "outline",
            children: ($$renderer5) => {
              Link($$renderer5, {
                href: "/setup/exam-materials",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form></div> <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs"><div class="space-y-1 border-b border-border pb-3"><h3 class="font-semibold text-sm text-foreground">Bundle Structure</h3> <p class="text-xs text-muted-foreground">Native OMRChecker package guide</p></div> <div class="space-y-3 text-sm"><div class="py-1 border-b border-border/50"><span class="font-mono text-xs font-semibold text-foreground">template.json</span> <p class="text-xs text-muted-foreground mt-0.5">Defines bubble grid coordinates, field names, and question mappings.</p></div> <div class="py-1 border-b border-border/50"><span class="font-mono text-xs font-semibold text-foreground">config.json</span> <p class="text-xs text-muted-foreground mt-0.5">Thresholding, marker detection, and processing parameters.</p></div> <div class="py-1 border-b border-border/50"><span class="font-mono text-xs font-semibold text-foreground">manifest.json</span> <p class="text-xs text-muted-foreground mt-0.5">Package metadata, versioning, author, and page order.</p></div> <div class="py-1 border-b border-border/50"><span class="font-mono text-xs font-semibold text-foreground">Reference SVGs</span> <p class="text-xs text-muted-foreground mt-0.5">Normalized vector templates used for optical homography alignment.</p></div></div> <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">Native bundles bypass browser-based authoring and are imported directly into the engine as verified canonical assets.</div></div></div></div>`);
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
  Intake as default
};
