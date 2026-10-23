import { e as escape_html, a as ensure_array_like, c as attr_class, B as Button, b as attr, d as derived, v as error, q as success } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import TestCapturePanel from "./TestCapturePanel-COWb1P5y.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { S as Sliders_vertical } from "./sliders-vertical-DiYN4y-t.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Pen_line } from "./pen-line-Bqgfmrgp.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
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
import "./OmrCaptureInput-CmVb1QoH.js";
/* empty css                                                       */
import "./upload-CwqN2Utd.js";
import "./loader-circle-_UEl10Lx.js";
import "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import "./maximize-2-u72Ahym6.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./layers-_Ie6y7G_.js";
import "./chevron-up-lhASJLRD.js";
import "./check-DK45Y9SJ.js";
import "./flask-conical-CPKNi8ld.js";
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { template = {}, pairs = [], capabilities = {}, errors = {} } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials", href: "/setup/exam-materials" },
      { label: template.code ?? "Template" }
    ];
    function statusVariant(status) {
      if (status === "active") return "default";
      if (status === "draft") return "secondary";
      return "outline";
    }
    function shortHash(value) {
      return value ? value.slice(0, 12) : "—";
    }
    let confirming = null;
    let retiring = false;
    let copyingVersionId = null;
    function cascadePairs(version) {
      const ids = new Set(version.pair_ids ?? []);
      return pairs.filter((pair) => ids.has(pair.id));
    }
    function requestRetire(kind, id, label, cascade = []) {
      confirming = { kind, id, label, pairs: cascade };
    }
    function confirmRetire() {
      if (!confirming || retiring) return;
      retiring = true;
      router.post("/setup/omr/retire", { kind: confirming.kind, id: confirming.id }, {
        preserveScroll: true,
        onSuccess: () => {
          success(`${confirming.label} retired.`);
          confirming = null;
          retiring = false;
        },
        onError: (errs) => {
          retiring = false;
          error(errs.omr_retire ?? "Could not retire.");
        }
      });
    }
    function copyToNewDraft(version) {
      if (copyingVersionId) return;
      copyingVersionId = version.id;
      router.post(
        `/setup/omr/templates/${version.id}/overlay`,
        {
          version_id: version.id,
          overlay: version.print_overlay_json ?? {}
        },
        {
          onFinish: () => {
            copyingVersionId = null;
          }
        }
      );
    }
    let sheetOpenVersionId = null;
    let sheetTitle = "";
    let sheetNameBoxLabel = "";
    let sheetHeaderLines = "";
    let sheetFooterLines = "";
    let sheetPerPageNotes = "";
    let sheetSaving = false;
    let testOpenVersionId = null;
    function toggleTestCapture(version) {
      testOpenVersionId = testOpenVersionId === version.id ? null : version.id;
    }
    let showTechDetails = {};
    function toggleSheetEditor(version) {
      if (sheetOpenVersionId === version.id) {
        sheetOpenVersionId = null;
        return;
      }
      sheetOpenVersionId = version.id;
      const ov = version.print_overlay_json ?? {};
      sheetTitle = ov.title ?? "";
      sheetNameBoxLabel = ov.name_box_label ?? "";
      sheetHeaderLines = (ov.header_lines ?? []).join("\n");
      sheetFooterLines = (ov.footer_lines ?? []).join("\n");
      sheetPerPageNotes = (ov.per_page ?? []).map((p) => `${p.page_no}: ${p.note}`).join("\n");
    }
    function saveSheet(version) {
      if (sheetSaving) return;
      sheetSaving = true;
      const overlay = {
        title: sheetTitle.trim(),
        name_box_label: sheetNameBoxLabel.trim(),
        header_lines: sheetHeaderLines.split("\n").map((l) => l.trim()).filter(Boolean),
        footer_lines: sheetFooterLines.split("\n").map((l) => l.trim()).filter(Boolean),
        per_page: sheetPerPageNotes.split("\n").map((l) => l.trim()).filter(Boolean).map((l) => {
          const match = l.match(/^(\d+)\s*:\s*(.+)$/);
          return match ? { page_no: Number(match[1]), note: match[2].trim() } : null;
        }).filter(Boolean)
      };
      router.post(`/setup/omr/templates/${version.id}/overlay`, { version_id: version.id, overlay }, {
        preserveScroll: true,
        onSuccess: () => {
          success("Sheet text saved.");
          sheetOpenVersionId = null;
          router.reload({ only: ["template"] });
        },
        onError: (errs) => {
          error(errs.omr_overlay ?? "Could not save sheet text.");
        },
        onFinish: () => {
          sheetSaving = false;
        }
      });
    }
    const versions = derived(() => template.versions ?? []);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          {
            let actions = function($$renderer5) {
              Button($$renderer5, {
                asChild: true,
                size: "sm",
                children: ($$renderer6) => {
                  Link($$renderer6, {
                    href: "/setup/exam-materials/answer-sheets",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Answer Sheet Studio`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: template.name ?? "Template",
              description: `${template.code ?? ""}${template.is_synthetic ? " · synthetic template" : ""}`,
              actions
            });
          }
          $$renderer4.push(`<!----> <section class="space-y-6 p-6">`);
          {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div><div class="flex items-center justify-between"><h2 class="text-foreground text-base font-semibold">Template versions</h2> `);
          Badge($$renderer4, {
            variant: "secondary",
            class: "text-xs",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(versions().length)} total`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> `);
          if (versions().length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border/80 text-muted-foreground mt-3 rounded-xl border border-dashed p-8 text-center text-sm">No versions registered for this template yet.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="mt-3 space-y-4"><!--[-->`);
            const each_array = ensure_array_like(versions());
            for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
              let version = each_array[$$index_1];
              const isDraft = version.status === "draft";
              const isActive = version.status === "active";
              cascadePairs(version);
              $$renderer4.push(`<div${attr_class(`rounded-xl border ${isActive ? "border-primary/40 bg-card shadow-xs" : "border-border/80 bg-card"} p-5 transition-all`)}><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2.5"><span class="text-foreground font-mono text-base font-bold">v${escape_html(version.version)}</span> `);
              Badge($$renderer4, {
                variant: statusVariant(version.status),
                class: "text-xs capitalize",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(version.status)}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> <span class="text-muted-foreground text-xs">${escape_html(version.page_width)}×${escape_html(version.page_height)}
                                        px · ${escape_html(version.page_count ?? 1)} page${escape_html(version.page_count === 1 ? "" : "s")}</span></div> <div class="flex flex-wrap items-center gap-2">`);
              Button($$renderer4, {
                variant: "outline",
                size: "sm",
                href: `/setup/omr/templates/${version.id}/preview`,
                target: "_blank",
                rel: "noopener",
                children: ($$renderer5) => {
                  Eye($$renderer5, { class: "mr-1.5 size-3.5" });
                  $$renderer5.push(`<!----> Preview sheet`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (version.capture_policy != null) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  onclick: () => toggleTestCapture(version),
                  title: "Dry Run a photo against this answer sheet version without writing grading data",
                  children: ($$renderer5) => {
                    Scan_line($$renderer5, { class: "mr-1.5 size-3.5" });
                    $$renderer5.push(`<!----> Dry Run`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              Button($$renderer4, {
                variant: "outline",
                size: "sm",
                onclick: () => toggleSheetEditor(version),
                children: ($$renderer5) => {
                  Sliders_vertical($$renderer5, { class: "mr-1.5 size-3.5" });
                  $$renderer5.push(`<!----> Sheet text`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (isDraft) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  asChild: true,
                  size: "sm",
                  children: ($$renderer5) => {
                    Link($$renderer5, {
                      href: "/setup/exam-materials/answer-sheets",
                      children: ($$renderer6) => {
                        Pen_line($$renderer6, { class: "mr-1.5 size-3.5" });
                        $$renderer6.push(`<!----> Open Answer Sheet Studio`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (isActive) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  size: "sm",
                  onclick: () => copyToNewDraft(version),
                  disabled: copyingVersionId === version.id,
                  children: ($$renderer5) => {
                    Copy($$renderer5, { class: "mr-1.5 size-3.5" });
                    $$renderer5.push(`<!----> ${escape_html(copyingVersionId === version.id ? "Copying…" : "New version from this")}`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (version.status !== "retired") {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "ghost",
                  size: "sm",
                  class: "text-muted-foreground hover:text-destructive",
                  onclick: () => requestRetire("template_version", version.id, `version v${version.version}`, cascadePairs(version)),
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Retire`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div></div> `);
              if (confirming?.kind === "template_version" && confirming.id === version.id) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive mt-4 rounded-lg border p-4 text-xs"><p class="font-semibold">Retire template version v${escape_html(version.version)}?</p> `);
                if (confirming.pairs.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="mt-1">This will also retire ${escape_html(confirming.pairs.length)} active scanning pair(s):</p> <ul class="mt-1 list-disc space-y-0.5 pl-5"><!--[-->`);
                  const each_array_1 = ensure_array_like(confirming.pairs);
                  for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                    let pair = each_array_1[$$index];
                    $$renderer4.push(`<li>${escape_html(pair.label)}</li>`);
                  }
                  $$renderer4.push(`<!--]--></ul>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<p class="mt-1">No active pairs are linked to this
                                            version.</p>`);
                }
                $$renderer4.push(`<!--]--> <div class="mt-3 flex gap-2">`);
                Button($$renderer4, {
                  size: "xs",
                  variant: "destructive",
                  onclick: confirmRetire,
                  disabled: retiring,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(retiring ? "Retiring…" : "Confirm retire")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Button($$renderer4, {
                  size: "xs",
                  variant: "outline",
                  onclick: () => confirming = null,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Cancel`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (sheetOpenVersionId === version.id) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="border-primary/30 bg-muted/30 mt-4 rounded-xl border p-4"><div class="flex items-center justify-between"><h4 class="text-foreground text-sm font-semibold">Custom printable sheet text (v${escape_html(version.version)})</h4> `);
                if (!isDraft) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<span class="text-muted-foreground text-xs">Saving changes on an active
                                                version creates a new editable
                                                draft.</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> `);
                if (errors.omr_overlay) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="mt-3 rounded bg-red-50 p-3 text-xs text-red-800">${escape_html(errors.omr_overlay)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> <div class="mt-3 grid gap-3 sm:grid-cols-2"><div class="space-y-1">`);
                Label($$renderer4, {
                  for: `sheet_title_${version.id}`,
                  class: "text-xs font-medium",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Sheet title`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Input($$renderer4, {
                  id: `sheet_title_${version.id}`,
                  placeholder: "e.g. Entrance Exam 2026 — Answer Sheet",
                  class: "h-8 text-xs",
                  get value() {
                    return sheetTitle;
                  },
                  set value($$value) {
                    sheetTitle = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----></div> <div class="space-y-1">`);
                Label($$renderer4, {
                  for: `sheet_name_box_${version.id}`,
                  class: "text-xs font-medium",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Name box label`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Input($$renderer4, {
                  id: `sheet_name_box_${version.id}`,
                  placeholder: "e.g. NAME (PRINT):",
                  class: "h-8 text-xs",
                  get value() {
                    return sheetNameBoxLabel;
                  },
                  set value($$value) {
                    sheetNameBoxLabel = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----></div></div> <div class="mt-3 grid gap-3 sm:grid-cols-2"><div class="space-y-1">`);
                Label($$renderer4, {
                  for: `sheet_header_${version.id}`,
                  class: "text-xs font-medium",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Header lines`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Textarea($$renderer4, {
                  id: `sheet_header_${version.id}`,
                  rows: "2",
                  placeholder: "One line per row\ne.g. EX-DEMO-1 · MAIN HALL 101",
                  class: "text-xs",
                  get value() {
                    return sheetHeaderLines;
                  },
                  set value($$value) {
                    sheetHeaderLines = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----></div> <div class="space-y-1">`);
                Label($$renderer4, {
                  for: `sheet_footer_${version.id}`,
                  class: "text-xs font-medium",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Footer lines`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Textarea($$renderer4, {
                  id: `sheet_footer_${version.id}`,
                  rows: "2",
                  placeholder: "One line per row\ne.g. Shade completely. Erasures void the sheet.",
                  class: "text-xs",
                  get value() {
                    return sheetFooterLines;
                  },
                  set value($$value) {
                    sheetFooterLines = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----></div></div> <div class="mt-3 flex items-center gap-2">`);
                Button($$renderer4, {
                  size: "xs",
                  onclick: () => saveSheet(version),
                  disabled: sheetSaving,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(sheetSaving ? "Saving…" : isDraft ? "Save sheet text" : "Save as new draft edition")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Button($$renderer4, {
                  size: "xs",
                  variant: "outline",
                  onclick: () => sheetOpenVersionId = null,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Cancel`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (testOpenVersionId === version.id) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="mt-4">`);
                TestCapturePanel($$renderer4, { versionId: version.id, markerBearing: true });
                $$renderer4.push(`<!----></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> <div class="border-border/50 mt-4 border-t pt-3"><button type="button" class="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs">`);
              if (showTechDetails[version.id]) {
                $$renderer4.push("<!--[0-->");
                Chevron_down($$renderer4, { class: "size-3.5" });
              } else {
                $$renderer4.push("<!--[-1-->");
                Chevron_right($$renderer4, { class: "size-3.5" });
              }
              $$renderer4.push(`<!--]--> <span>Technical hashes &amp; engine
                                        specifications</span></button> `);
              if (showTechDetails[version.id]) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<dl class="bg-muted/30 mt-3 grid gap-x-6 gap-y-1.5 rounded-lg p-3 text-xs sm:grid-cols-2"><div class="border-border/40 flex justify-between border-b py-1"><dt class="text-muted-foreground">Engine:</dt> <dd class="text-foreground font-mono">${escape_html(version.engine_name)} @ ${escape_html(version.engine_commit?.slice(0, 8))}</dd></div> <div class="border-border/40 flex justify-between border-b py-1"><dt class="text-muted-foreground">Patch identity:</dt> <dd class="text-foreground font-mono">${escape_html(version.patch_identity ?? "—")}</dd></div> <div class="border-border/40 flex justify-between border-b py-1"><dt class="text-muted-foreground">Bundle hash:</dt> <dd class="text-foreground font-mono"${attr("title", version.native_bundle_hash)}>${escape_html(shortHash(version.native_bundle_hash))}…</dd></div> <div class="border-border/40 flex justify-between border-b py-1"><dt class="text-muted-foreground">Config hash:</dt> <dd class="text-foreground font-mono"${attr("title", version.config_hash)}>${escape_html(shortHash(version.config_hash))}…</dd></div></dl>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div></div>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          }
          $$renderer4.push(`<!--]--></div> <div><h2 class="text-foreground text-base font-semibold">Linked scanning pairs</h2> <p class="text-muted-foreground text-xs">Pairs using versions of this answer sheet.</p> `);
          if (pairs.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground mt-2 text-xs">No pairs registered for this template.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="mt-3 space-y-2"><!--[-->`);
            const each_array_2 = ensure_array_like(pairs);
            for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
              let pair = each_array_2[$$index_2];
              $$renderer4.push(`<div class="border-border/70 bg-card flex items-center justify-between rounded-xl border p-3.5 text-xs"><span class="text-foreground font-medium">${escape_html(pair.label)}</span> <div class="flex items-center gap-2">`);
              Badge($$renderer4, {
                variant: pair.status === "active" ? "default" : "outline",
                class: "text-[10px] capitalize",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(pair.status)}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (pair.status === "active") {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  size: "xs",
                  onclick: () => requestRetire("pair", pair.id, pair.label, []),
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Retire`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></div></div>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          }
          $$renderer4.push(`<!--]--></div></section>`);
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
  Show as default
};
