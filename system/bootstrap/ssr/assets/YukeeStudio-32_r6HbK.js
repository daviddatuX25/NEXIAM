import { g as sanitize_props, i as spread_props, j as slot, h as head, c as attr_class, e as escape_html, a as ensure_array_like, b as attr, B as Button, f as attr_style } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { I as InfoPopover } from "./InfoPopover-CdH5YJnv.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { R as Radar } from "./radar-CMV2oDsL.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { I as Info } from "./info-C3MCzlQW.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Cpu } from "./cpu-B3JGJjJ9.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
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
import "./dialog-footer-kNnwKT2h.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./chevron-left-DCSOZwZl.js";
function Award($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"
      }
    ],
    ["circle", { "cx": "12", "cy": "8", "r": "6" }]
  ];
  Icon($$renderer, spread_props([
    { name: "award" },
    $$sanitized_props,
    {
      /**
       * @component @name Award
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJtMTUuNDc3IDEyLjg5IDEuNTE1IDguNTI2YS41LjUgMCAwIDEtLjgxLjQ3bC0zLjU4LTIuNjg3YTEgMSAwIDAgMC0xLjE5NyAwbC0zLjU4NiAyLjY4NmEuNS41IDAgMCAxLS44MS0uNDY5bDEuNTE0LTguNTI2IiAvPgogIDxjaXJjbGUgY3g9IjEyIiBjeT0iOCIgcj0iNiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/award
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
function Cloud_upload($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 13v8" }],
    [
      "path",
      {
        "d": "M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"
      }
    ],
    ["path", { "d": "m8 17 4-4 4 4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "cloud-upload" },
    $$sanitized_props,
    {
      /**
       * @component @name CloudUpload
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMTN2OCIgLz4KICA8cGF0aCBkPSJNNCAxNC44OTlBNyA3IDAgMSAxIDE1LjcxIDhoMS43OWE0LjUgNC41IDAgMCAxIDIuNSA4LjI0MiIgLz4KICA8cGF0aCBkPSJtOCAxNyA0LTQgNCA0IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/cloud-upload
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
function YukeeStudio($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { cycle = null, programs = [], aptitude_areas = [] } = $$props;
    let activeTab = "overview";
    const clusters = [
      {
        id: 0,
        name: "All-Around Strong",
        badge: "High Balanced Profile",
        variant: "default",
        description: "Consistently high percentiles across both cognitive and psychomotor domains. Suitable for board-regulated and intensive academic programs.",
        recommendedPrograms: ["BSN", "BSCE", "BSCS", "BSEd-Math"],
        centroids: {
          LOGIC: 92,
          PROFILE_VERBAL: 89,
          PROFILE_QUANT: 94,
          SPATIAL: 90,
          MEMORY: 88,
          LANGUAGE: 86
        }
      },
      {
        id: 1,
        name: "Cognitive-Oriented",
        badge: "Analytical / Conceptual",
        variant: "secondary",
        description: "Strong performance in abstract problem solving, verbal reasoning, and numerical facility, with moderate psychomotor dexterity.",
        recommendedPrograms: ["BSIT", "BSCS", "BSBA", "BSPSY", "BSEd-Eng"],
        centroids: {
          LOGIC: 88,
          PROFILE_VERBAL: 86,
          PROFILE_QUANT: 85,
          SPATIAL: 76,
          MEMORY: 75,
          LANGUAGE: 72
        }
      },
      {
        id: 2,
        name: "Practical-Oriented",
        badge: "Spatial / Technical Hands-on",
        variant: "outline",
        description: "Strong spatial perception, clerical accuracy, and manual dexterity. Excels in technical, operational, and vocational degree courses.",
        recommendedPrograms: ["BAT", "BSHM", "BSCE", "BSIT"],
        centroids: {
          LOGIC: 74,
          PROFILE_VERBAL: 70,
          PROFILE_QUANT: 75,
          SPATIAL: 88,
          MEMORY: 86,
          LANGUAGE: 89
        }
      }
    ];
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      {
        label: "Admission Decision Support",
        href: "/setup/admission-support"
      },
      { label: "ICAT Aptitude Profiling" }
    ];
    head("1df8f27", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>ICAT Aptitude Profiling (YuKee) · Setup · NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.82_0.12_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.9_0.08_85_/_0.12),transparent_40%)] dark:bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.38_0.1_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.35_0.07_85_/_0.12),transparent_40%)]" aria-hidden="true"></div> <div class="min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">`);
        {
          let actions = function($$renderer4) {
            $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
            Badge($$renderer4, {
              variant: "outline",
              class: "gap-1.5 border-primary/30 bg-primary/5 px-2.5 py-1 text-xs",
              children: ($$renderer5) => {
                Clock($$renderer5, { class: "size-3 text-primary" });
                $$renderer5.push(`<!----> <span>${escape_html(cycle?.name ?? "No active cycle")}</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Badge($$renderer4, {
              variant: "secondary",
              class: "gap-1.5 border border-muted-foreground/30 bg-muted/20 px-2.5 py-1 text-xs",
              children: ($$renderer5) => {
                Radar($$renderer5, { class: "size-3 text-muted-foreground" });
                $$renderer5.push(`<!----> <span>Research Model · Standby</span>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            InfoPopover($$renderer4, {
              title: "Institutional Research Foundation",
              content: "'Clustering Student ICAT Score Using Machine Learning Algorithm' by Azriel Jon M. Yukee, Christine Lorence Bonifacio, John Mark A. Salvador, and Aries P. Macabitas (BSIT ISPSC Tagudin, Dec 2025; Statistician: George R. Villanueva Jr., DTL)."
            });
            $$renderer4.push(`<!----></div>`);
          };
          PageHeaderBar($$renderer3, {
            title: "ICAT Aptitude Profiling Studio",
            description: "Cognitive learner archetypes and research model verification based on campus student aptitude research.",
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="flex overflow-x-auto sm:flex-wrap items-center gap-1.5 rounded-xl border border-border/60 bg-muted/30 p-1.5 backdrop-blur-sm scrollbar-none snap-x"><button type="button"${attr_class(`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${activeTab === "overview" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"}`)}>`);
        Book_open($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Overview &amp; Guide</span></button> <button type="button"${attr_class(`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${activeTab === "clusters" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"}`)}>`);
        Radar($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Learner Clusters &amp; Centroids</span></button> <button type="button"${attr_class(`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${activeTab === "alignment" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"}`)}>`);
        Award($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Program Alignment</span></button> <button type="button"${attr_class(`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${activeTab === "artifact" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-muted/50 hover:text-foreground"}`)}>`);
        Cloud_upload($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Model Artifact &amp; Verification</span></button></div>  `);
        if (activeTab === "overview") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-6">`);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "overflow-hidden border-border/60 bg-card/80 shadow-sm backdrop-blur-sm dark:bg-card/90",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="border-b border-border/50 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-4 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div class="flex min-w-0 flex-1 items-center gap-3"><div class="flex size-11 sm:size-12 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary">`);
                Radar($$renderer4, { class: "size-5 sm:size-6" });
                $$renderer4.push(`<!----></div> <div class="min-w-0 flex-1"><h3 class="text-base sm:text-lg font-semibold tracking-tight text-foreground truncate">ICAT Aptitude Profiling (YuKee K-Means)</h3> <p class="text-xs text-muted-foreground truncate">Unsupervised Cognitive Aptitude Clustering · BSIT ISPSC Tagudin Campus Research</p></div></div> <div class="flex flex-wrap items-center gap-2 shrink-0">`);
                Badge($$renderer4, {
                  variant: "outline",
                  class: "gap-1.5 border-primary/30 bg-primary/5 px-2.5 py-1 text-xs",
                  children: ($$renderer5) => {
                    Sparkles($$renderer5, { class: "size-3 text-primary" });
                    $$renderer5.push(`<!----> <span>Unsupervised K-Means (K=4)</span>`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Badge($$renderer4, {
                  variant: "secondary",
                  class: "gap-1.5 px-2.5 py-1 text-xs",
                  children: ($$renderer5) => {
                    Circle_check($$renderer5, { class: "size-3 text-emerald-600" });
                    $$renderer5.push(`<!----> <span>Independent Evidence Slot</span>`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div></div></div> `);
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "space-y-6 p-4 pt-4 sm:p-6 sm:pt-6",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="grid gap-4 md:grid-cols-3"><div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Radar($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----></div> <h4 class="text-xs font-semibold text-foreground">Multidimensional Clustering</h4></div> <p class="text-xs leading-relaxed text-muted-foreground">Instead of reducing an applicant to a single pass/fail ranking, K-Means groups applicants across distinct cognitive domains into natural learner archetypes without human labeling bias.</p></div> <div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Award($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----></div> <h4 class="text-xs font-semibold text-foreground">Guidance Radar Profiles</h4></div> <p class="text-xs leading-relaxed text-muted-foreground">Produces 6-axis radar charts comparing applicant percentiles to cluster centroids. Counselors easily identify hidden strengths (e.g. high spatial dexterity even with average verbal scores).</p></div> <div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Circle_check($$renderer5, { class: "size-4 text-emerald-600" });
                      $$renderer5.push(`<!----></div> <h4 class="text-xs font-semibold text-foreground">Direct Model Adoption</h4></div> <p class="text-xs leading-relaxed text-muted-foreground">Adopts the pre-trained campus research model file (<code>.joblib</code>) directly without retraining, backed by built-in fallback profiling so student consultation is never interrupted.</p></div></div> <div class="space-y-4 rounded-xl border border-border/50 bg-muted/10 p-5"><div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-3"><div><h4 class="text-sm font-semibold text-foreground">Active Institutional Configuration</h4> <p class="text-xs text-muted-foreground">Degree programs and evaluated cognitive areas from system database records.</p></div> <div class="flex items-center gap-2 font-mono text-xs text-muted-foreground"><span>Cycle: <strong class="text-foreground">${escape_html(cycle?.name ?? "No active cycle")}</strong></span></div></div> <div class="space-y-2"><div class="flex items-center justify-between"><span class="text-xs font-medium text-foreground">Active Academic Programs (${escape_html(programs.length)} configured):</span> <span class="text-[11px] text-muted-foreground">Programs Registry</span></div> <div class="flex flex-wrap gap-1.5"><!--[-->`);
                      const each_array = ensure_array_like(programs);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let prog = each_array[$$index];
                        $$renderer5.push(`<span class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-background/80 px-2 py-1 text-xs"${attr("title", prog.name)}><span class="font-mono font-semibold text-primary">${escape_html(prog.code)}</span> <span class="max-w-[140px] truncate text-muted-foreground">${escape_html(prog.name)}</span></span>`);
                      }
                      $$renderer5.push(`<!--]--></div></div> <div class="space-y-2 pt-2 border-t border-border/30"><div class="flex items-center justify-between"><span class="text-xs font-medium text-foreground">Evaluated Cognitive Aptitude Domains (${escape_html(aptitude_areas.length)} configured):</span> <span class="text-[11px] text-muted-foreground">Standardized assessment sub-tests</span></div> <div class="flex flex-wrap gap-1.5"><!--[-->`);
                      const each_array_1 = ensure_array_like(aptitude_areas);
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let area = each_array_1[$$index_1];
                        Badge($$renderer5, {
                          variant: "secondary",
                          class: "gap-1 px-2.5 py-1 text-xs",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<span>${escape_html(area.name)}</span> <span class="font-mono text-[10px] text-muted-foreground">(${escape_html(area.code)})</span>`);
                          },
                          $$slots: { default: true }
                        });
                      }
                      $$renderer5.push(`<!--]--></div></div></div> <div class="space-y-3 rounded-xl border border-border/50 bg-muted/10 p-5"><h4 class="text-sm font-semibold text-foreground">The 4 Natural Learner Archetypes (K=4)</h4> <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><!--[-->`);
                      const each_array_2 = ensure_array_like(clusters);
                      for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                        let cluster = each_array_2[$$index_2];
                        $$renderer5.push(`<div class="space-y-1.5 rounded-lg border border-border/40 bg-background/60 p-3.5"><div class="flex items-center justify-between gap-1">`);
                        Badge($$renderer5, {
                          variant: cluster.variant,
                          class: "text-[10px] font-mono",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Cluster ${escape_html(cluster.id)}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----> <span class="text-[10px] text-muted-foreground">${escape_html(cluster.badge)}</span></div> <h5 class="text-xs font-semibold text-foreground">${escape_html(cluster.name)}</h5> <p class="text-xs leading-relaxed text-muted-foreground line-clamp-3">${escape_html(cluster.description)}</p></div>`);
                      }
                      $$renderer5.push(`<!--]--></div></div> <div class="space-y-3"><h4 class="text-sm font-semibold text-foreground">Studio Workspaces</h4> <div class="grid gap-4 md:grid-cols-3"><div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm"><div class="space-y-1.5"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Radar($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----></div> <h5 class="text-xs font-semibold text-foreground">Clusters &amp; Centroids</h5></div> <p class="text-xs text-muted-foreground">Inspect 6-axis percentile centroids, cluster sizes, and individual aptitude breakdown per archetype.</p></div> <div class="pt-4">`);
                      Button($$renderer5, {
                        size: "sm",
                        class: "w-full gap-1.5 text-xs",
                        onclick: () => activeTab = "clusters",
                        children: ($$renderer6) => {
                          Radar($$renderer6, { class: "size-3.5" });
                          $$renderer6.push(`<!----> <span>View Centroids</span> `);
                          Chevron_right($$renderer6, { class: "size-3 ml-auto text-muted-foreground" });
                          $$renderer6.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div></div> <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm"><div class="space-y-1.5"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Award($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----></div> <h5 class="text-xs font-semibold text-foreground">Program Alignment</h5></div> <p class="text-xs text-muted-foreground">Review degree program mapping, board curriculum requirements, and key cognitive areas.</p></div> <div class="pt-4">`);
                      Button($$renderer5, {
                        variant: "outline",
                        size: "sm",
                        class: "w-full gap-1.5 text-xs",
                        onclick: () => activeTab = "alignment",
                        children: ($$renderer6) => {
                          Award($$renderer6, { class: "size-3.5" });
                          $$renderer6.push(`<!----> <span>View Alignment</span> `);
                          Chevron_right($$renderer6, { class: "size-3 ml-auto text-muted-foreground" });
                          $$renderer6.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div></div> <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm"><div class="space-y-1.5"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                      Cloud_upload($$renderer5, { class: "size-4" });
                      $$renderer5.push(`<!----></div> <h5 class="text-xs font-semibold text-foreground">Artifact Dropzone</h5></div> <p class="text-xs text-muted-foreground">Validate serialized .joblib pipeline, scikit-learn dependency versions, and ground truth tests.</p></div> <div class="pt-4">`);
                      Button($$renderer5, {
                        variant: "outline",
                        size: "sm",
                        class: "w-full gap-1.5 text-xs",
                        onclick: () => activeTab = "artifact",
                        children: ($$renderer6) => {
                          Cloud_upload($$renderer6, { class: "size-3.5 text-primary" });
                          $$renderer6.push(`<!----> <span>Artifact &amp; Verification</span> `);
                          Chevron_right($$renderer6, { class: "size-3 ml-auto text-muted-foreground" });
                          $$renderer6.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div></div></div></div> <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4"><div class="flex items-center gap-2 text-xs text-muted-foreground">`);
                      Info($$renderer5, { class: "size-4 text-primary" });
                      $$renderer5.push(`<!----> <span>Looking for in-context curriculum &amp; benchmark course matching?</span></div> `);
                      Link($$renderer5, {
                        href: "/setup/admission-support/tabfm",
                        children: ($$renderer6) => {
                          Button($$renderer6, {
                            variant: "ghost",
                            size: "sm",
                            class: "gap-1.5 text-xs",
                            children: ($$renderer7) => {
                              Cpu($$renderer7, { class: "size-3.5 text-primary" });
                              $$renderer7.push(`<!----> <span>Explore Curriculum &amp; Benchmark Matching (TabFM Studio) →</span>`);
                            },
                            $$slots: { default: true }
                          });
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(`</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->  `);
        if (activeTab === "artifact") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-6">`);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "border-amber-500/30 bg-amber-500/[0.04]",
              children: ($$renderer4) => {
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "p-4 sm:p-6",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex items-start gap-3.5">`);
                      Shield_alert($$renderer5, { class: "size-6 shrink-0 text-amber-500" });
                      $$renderer5.push(`<!----> <div class="space-y-1.5"><h3 class="text-sm font-semibold text-foreground">Awaiting Campus Research Model File (.joblib)</h3> <p class="text-xs leading-relaxed text-muted-foreground">To run the research model in live consultation, the system directly adopts the researchers' saved model file (<code>.joblib</code>) without requiring model retraining. Verification requires the model file, the matching Python/library versions used during research, and 3–5 sample student test cases to ensure identical prediction results.</p></div></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><div class="rounded-xl border border-border/50 bg-card/60 p-4"><p class="text-[11px] font-medium uppercase text-muted-foreground">Expected File Path</p> <p class="mt-1 font-mono text-xs font-semibold text-foreground break-all">system/storage/app/models/yukee_kmeans.joblib</p> <p class="mt-2 text-xs text-amber-600 dark:text-amber-400 font-medium">● File absent — Standby mode active</p></div> <div class="rounded-xl border border-border/50 bg-card/60 p-4"><p class="text-[11px] font-medium uppercase text-muted-foreground">Algorithm &amp; Packaging</p> <p class="mt-1 text-sm font-semibold text-foreground">K-Means Clustering</p> <p class="mt-2 font-mono text-xs text-muted-foreground">K = 3 clusters · scikit-learn pipeline</p></div> <div class="rounded-xl border border-border/50 bg-card/60 p-4"><p class="text-[11px] font-medium uppercase text-muted-foreground">Verification Checksum</p> <p class="mt-1 font-mono text-xs font-semibold text-foreground">SHA-256 Integrity Lock</p> <p class="mt-2 text-xs text-muted-foreground">Artifact is hashed and pinned before runtime inference.</p></div></div> `);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "border-border/60",
              children: ($$renderer4) => {
                if (Card_header) {
                  $$renderer4.push("<!--[-->");
                  Card_header($$renderer4, {
                    class: "border-b border-border/50 pb-4",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex items-center gap-1.5">`);
                      if (Card_title) {
                        $$renderer5.push("<!--[-->");
                        Card_title($$renderer5, {
                          class: "text-base",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Artifact Delivery Instructions`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      InfoPopover($$renderer5, {
                        content: "Integration requires reproducible loading from the serialized .joblib artifact, pinned scikit-learn dependency versions, and ground-truth verification vectors."
                      });
                      $$renderer5.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "space-y-4 pt-4 text-xs leading-relaxed text-muted-foreground",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<ol class="list-inside list-decimal space-y-2"><li><strong class="text-foreground">Export Model Pipeline:</strong> Save the model using <code>joblib.dump(model, 'yukee_kmeans.joblib')</code> in Python, ensuring any feature normalizer or scaler is included in the pipeline.</li> <li><strong class="text-foreground">Supply Software Environment:</strong> Provide the <code>scikit-learn</code>, <code>numpy</code>, and <code>python</code> version numbers used by the research team.</li> <li><strong class="text-foreground">Supply Verification Profiles:</strong> Provide 3–5 sample student ICAT scores with their expected cluster assignments so automated verification tests pass before activating.</li></ol>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(`</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->  `);
        if (activeTab === "clusters") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-6"><div class="grid gap-4 sm:grid-cols-3"><div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center"><p class="text-[11px] font-medium uppercase text-muted-foreground">Silhouette Score</p> <p class="mt-1 text-2xl font-bold font-mono text-foreground">Validated</p> <p class="mt-1 text-xs text-muted-foreground">Tested across candidate K values</p></div> <div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center"><p class="text-[11px] font-medium uppercase text-muted-foreground">Optimal K Selection</p> <p class="mt-1 text-2xl font-bold font-mono text-foreground">Elbow Method</p> <p class="mt-1 text-xs text-muted-foreground">3 natural clusters identified</p></div> <div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center"><p class="text-[11px] font-medium uppercase text-muted-foreground">Cluster Separation</p> <p class="mt-1 text-2xl font-bold font-mono text-foreground">Davies-Bouldin</p> <p class="mt-1 text-xs text-muted-foreground">Calinski-Harabasz verified</p></div></div> <div class="space-y-4"><div class="flex items-center gap-1.5"><h3 class="text-sm font-semibold text-foreground">Discovered Student Clusters</h3> `);
          InfoPopover($$renderer3, {
            content: "Campus research by Yukee et al. (2025). Natural groupings discovered using the CRISP-DM methodology across 6 ICAT sub-tests."
          });
          $$renderer3.push(`<!----></div> <div class="grid gap-5 lg:grid-cols-3"><!--[-->`);
          const each_array_3 = ensure_array_like(clusters);
          for (let $$index_5 = 0, $$length = each_array_3.length; $$index_5 < $$length; $$index_5++) {
            let c = each_array_3[$$index_5];
            $$renderer3.push(`<div class="flex flex-col justify-between rounded-xl border border-border/60 bg-card/75 p-5 shadow-sm"><div class="space-y-3"><div class="flex items-center justify-between">`);
            Badge($$renderer3, {
              variant: "outline",
              class: "font-mono text-[10px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Cluster #${escape_html(c.id)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            Badge($$renderer3, {
              variant: c.variant,
              class: "text-[11px]",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(c.badge)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> <div><h4 class="text-base font-semibold text-foreground">${escape_html(c.name)}</h4> <p class="mt-1 text-xs leading-relaxed text-muted-foreground">${escape_html(c.description)}</p></div> <div class="space-y-2 border-t border-border/40 pt-3"><p class="text-[10px] font-semibold uppercase text-muted-foreground">Centroid Profile (Percentile):</p> <!--[-->`);
            const each_array_4 = ensure_array_like(Object.entries(c.centroids));
            for (let $$index_3 = 0, $$length2 = each_array_4.length; $$index_3 < $$length2; $$index_3++) {
              let [dim, val] = each_array_4[$$index_3];
              $$renderer3.push(`<div class="space-y-1"><div class="flex justify-between text-[11px]"><span class="text-muted-foreground font-mono">${escape_html(dim)}</span> <span class="font-mono font-semibold text-foreground tabular-nums">${escape_html(val)}</span></div> <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary"${attr_style(`width: ${val}%`)}></div></div></div>`);
            }
            $$renderer3.push(`<!--]--></div></div> <div class="mt-4 border-t border-border/40 pt-3"><p class="text-[10px] font-medium text-muted-foreground">Recommended Programs:</p> <div class="mt-1 flex flex-wrap gap-1"><!--[-->`);
            const each_array_5 = ensure_array_like(c.recommendedPrograms);
            for (let $$index_4 = 0, $$length2 = each_array_5.length; $$index_4 < $$length2; $$index_4++) {
              let p = each_array_5[$$index_4];
              Badge($$renderer3, {
                variant: "secondary",
                class: "font-mono text-[10px]",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(p)}`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer3.push(`<!--]--></div></div></div>`);
          }
          $$renderer3.push(`<!--]--></div></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->  `);
        if (activeTab === "alignment") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-4">`);
          if (Card) {
            $$renderer3.push("<!--[-->");
            Card($$renderer3, {
              class: "border-border/60",
              children: ($$renderer4) => {
                if (Card_header) {
                  $$renderer4.push("<!--[-->");
                  Card_header($$renderer4, {
                    class: "border-b border-border/50 pb-4",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="flex items-center gap-1.5">`);
                      if (Card_title) {
                        $$renderer5.push("<!--[-->");
                        Card_title($$renderer5, {
                          class: "text-base",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Advisory Program Alignment Matrix`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      InfoPopover($$renderer5, {
                        content: "Non-authoritative guidance reference derived from the cluster findings for ISPSC Tagudin degree programs."
                      });
                      $$renderer5.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "p-0",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<div class="overflow-x-auto"><table class="w-full min-w-[580px] text-left text-xs"><thead class="border-b border-border/50 bg-muted/30 font-medium text-muted-foreground"><tr><th class="px-4 py-3">Degree Program</th><th class="px-4 py-3">Primary Aligned Cluster</th><th class="px-4 py-3">Key Required Aptitudes</th><th class="px-4 py-3">Advisory Level</th></tr></thead><tbody class="divide-y divide-border/40"><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BS Nursing (BSN)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "default",
                        class: "bg-emerald-600 text-white",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 0: All-Around Strong`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">High Science, Logic &amp; Manual Dexterity</td><td class="px-4 py-3 text-muted-foreground">Board program cutoff requirement</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BS Civil Engineering (BSCE)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "default",
                        class: "bg-emerald-600 text-white",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 0 / Cluster 2`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">High Quantitative &amp; Spatial Aptitude</td><td class="px-4 py-3 text-muted-foreground">Technical licensure course</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BS Information Technology (BSIT)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "secondary",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 1: Cognitive-Oriented`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">Logic, Quantitative &amp; Spatial</td><td class="px-4 py-3 text-muted-foreground">Direct computing field alignment</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BS Computer Science (BSCS)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "secondary",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 1: Cognitive-Oriented`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">High Logic &amp; Quantitative Aptitude</td><td class="px-4 py-3 text-muted-foreground">Theoretical and algorithmic track</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BSEd Major in English</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "secondary",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 1: Cognitive-Oriented`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">Verbal &amp; Language Aptitude</td><td class="px-4 py-3 text-muted-foreground">Teacher education board track</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">Bachelor in Agricultural Tech (BAT)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "outline",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 2: Practical-Oriented`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">Spatial, Perceptual &amp; Manual Dexterity</td><td class="px-4 py-3 text-muted-foreground">Hands-on agricultural systems</td></tr><tr class="hover:bg-muted/10"><td class="px-4 py-3 font-semibold text-foreground">BS Hospitality Management (BSHM)</td><td class="px-4 py-3">`);
                      Badge($$renderer5, {
                        variant: "outline",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Cluster 2: Practical-Oriented`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></td><td class="px-4 py-3 font-mono text-muted-foreground">Perceptual &amp; Manual Dexterity</td><td class="px-4 py-3 text-muted-foreground">Hospitality and operations track</td></tr></tbody></table></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(`</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  YukeeStudio as default
};
