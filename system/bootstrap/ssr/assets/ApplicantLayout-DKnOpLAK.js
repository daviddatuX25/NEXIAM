import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, c as attr_class, B as Button, a as ensure_array_like, d as derived, u as usePage } from "../app.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import { M as Menu, P as PreferencesMenu } from "./PreferencesMenu-CCPeta-5.js";
import { N as NotificationBell } from "./NotificationBell-Cgc59min.js";
import { I as InstitutionalBackdrop } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import "qrcode";
import { L as Link } from "./Link-B7ks7rBK.js";
import { X } from "./x-CnxrMbMn.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { L as Log_out, M as Megaphone } from "./megaphone-C8QUuT5s.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { S as Shield } from "./shield-DTtuUihb.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { C as Calendar_clock } from "./calendar-clock-D72LURiz.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./noop-BQS7OFs8.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
function Compass($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["circle", { "cx": "12", "cy": "12", "r": "10" }],
    [
      "path",
      {
        "d": "m16.24 7.76-1.804 5.411a2 2 0 0 1-1.265 1.265L7.76 16.24l1.804-5.411a2 2 0 0 1 1.265-1.265z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "compass" },
    $$sanitized_props,
    {
      /**
       * @component @name Compass
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIgLz4KICA8cGF0aCBkPSJtMTYuMjQgNy43Ni0xLjgwNCA1LjQxMWEyIDIgMCAwIDEtMS4yNjUgMS4yNjVMNy43NiAxNi4yNGwxLjgwNC01LjQxMWEyIDIgMCAwIDEgMS4yNjUtMS4yNjV6IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/compass
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
function ApplicantLayout($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      children,
      title = "Your status",
      breadcrumbs = [],
      wide = false
    } = $$props;
    const page = usePage();
    const user = derived(() => page.props.auth?.user ?? null);
    const rawNav = derived(() => page.props.applicant_navigation ?? []);
    const currentPath = derived(() => page.url.split("?")[0]);
    const qrData = derived(() => page.props.applicant_qr ?? null);
    const pageTitle = derived(() => breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1].label : title);
    let sidebarOpen = false;
    let qrModalOpen = false;
    let qrTourOpen = false;
    function logout() {
      router.post("/logout");
    }
    function closeSidebar() {
      sidebarOpen = false;
    }
    function iconForItem(key) {
      switch (key) {
        case "portal":
          return Compass;
        case "application":
          return File_text;
        case "schedule":
          return Calendar_clock;
        case "consultation":
          return User_check;
        case "program_choice":
          return Graduation_cap;
        case "admission_support":
          return Sparkles;
        case "office_inquiries":
          return Circle_question_mark;
        case "policies":
          return Shield;
        case "announcements":
          return Megaphone;
        default:
          return Compass;
      }
    }
    const navItems = derived(() => {
      const filtered = (rawNav() ?? []).filter((item) => item.key !== "notifications" && item.key !== "qr");
      const hasPolicies = filtered.some((item) => item.key === "policies" || item.href === "/policies");
      const hasAnnouncements = filtered.some((item) => item.key === "announcements" || item.href === "/announcements");
      const result = [...filtered];
      if (!hasPolicies) {
        result.push({
          key: "policies",
          label: "Public policies",
          href: "/policies",
          badge: "New"
        });
      }
      if (!hasAnnouncements) {
        result.push({
          key: "announcements",
          label: "Announcements",
          href: "/announcements",
          badge: "New"
        });
      }
      return result;
    });
    const applicationHref = derived(() => navItems().find((item) => item.key === "application")?.href ?? "/applicant/portal");
    const qrWalkthroughContext = derived(() => {
      const hasQ = Boolean(qrData()?.qr);
      const attemptId = qrData()?.attempt_id ? Number(qrData().attempt_id) : null;
      return {
        entityKey: attemptId ? `attempt-${attemptId}` : "my-qr",
        safetyKey: `ready:${attemptId ?? "noatt"}:${hasQ ? "qr" : "noqr"}`,
        freshness: "current",
        pageState: "ready",
        hasQr: hasQ,
        attemptId
      };
    });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("bnx9hg", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(pageTitle())} - NEXIAM</title>`);
        });
      });
      $$renderer3.push(`<div class="min-h-dvh text-foreground relative overflow-x-hidden"><div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_82%_15%,oklch(0.82_0.12_145_/_0.22),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.9_0.08_85_/_0.25),transparent_35%)] dark:bg-[radial-gradient(circle_at_82%_15%,oklch(0.38_0.1_145_/_0.18),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.35_0.07_85_/_0.15),transparent_35%)]"></div> `);
      InstitutionalBackdrop($$renderer3, { fixed: true });
      $$renderer3.push(`<!----> `);
      ToastManager($$renderer3, {});
      $$renderer3.push(`<!----> `);
      if (sidebarOpen) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button" class="fixed inset-0 z-40 bg-black/50 md:hidden print:hidden" aria-label="Close sidebar"></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <aside${attr_class(`fixed inset-y-0 left-0 z-50 w-60 flex-shrink-0 glass-panel border-r flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0 ${sidebarOpen ? "translate-x-0" : ""} print:hidden`)}><div class="flex flex-col h-full overflow-hidden"><div class="h-16 flex items-center px-4 border-b border-border/50 shrink-0">`);
      Link($$renderer3, {
        href: "/applicant/portal",
        class: "flex items-center gap-2.5 text-lg font-bold tracking-tight text-foreground transition-opacity hover:opacity-85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md",
        onclick: closeSidebar,
        children: ($$renderer4) => {
          $$renderer4.push(`<span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground text-sm font-extrabold shadow-xs" aria-hidden="true">N</span> <span class="font-bold text-base tracking-tight text-foreground">NEXIAM</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Button($$renderer3, {
        variant: "ghost",
        size: "icon",
        class: "md:hidden ml-auto",
        onclick: closeSidebar,
        "aria-label": "Close menu",
        children: ($$renderer4) => {
          X($$renderer4, { class: "h-5 w-5" });
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> <nav class="flex-1 overflow-y-auto scrollbar-hide p-2.5 space-y-1" aria-label="Applicant navigation"><p class="px-3 pt-1.5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/70">Applicant Menu</p> <!--[-->`);
      const each_array = ensure_array_like(navItems());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let item = each_array[$$index];
        const Icon2 = iconForItem(item.key);
        const isActive = currentPath() === item.href || item.key === "portal" && currentPath() === "/applicant/portal";
        Link($$renderer3, {
          href: item.href,
          class: `nav-item flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 ${isActive ? "bg-primary text-primary-foreground font-semibold shadow-xs" : "text-foreground/80 hover:text-foreground hover:bg-accent"}`,
          "aria-current": isActive ? "page" : void 0,
          onclick: closeSidebar,
          children: ($$renderer4) => {
            if (Icon2) {
              $$renderer4.push("<!--[-->");
              Icon2($$renderer4, { class: "size-4 shrink-0", "aria-hidden": "true" });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` <span class="flex-1 truncate">${escape_html(item.label)}</span> `);
            if (item.badge) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span${attr_class(`rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${isActive ? "bg-primary-foreground/20 text-primary-foreground" : "bg-primary/15 text-primary"}`)}>${escape_html(item.badge)}</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer3.push(`<!--]--></nav></div> <div class="p-3 pb-0 shrink-0">`);
      Button($$renderer3, {
        variant: "default",
        size: "lg",
        class: "w-full min-h-[44px] gap-2 font-semibold shadow-sm bg-primary text-primary-foreground hover:bg-primary/90",
        onclick: () => {
          qrModalOpen = true;
          closeSidebar();
        },
        "data-tour": "applicant-sidebar-qr",
        children: ($$renderer4) => {
          Qr_code($$renderer4, { class: "size-4.5" });
          $$renderer4.push(`<!----> <span>My QR Pass</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> <div class="border-t border-border/50 p-3 shrink-0"><div class="relative flex items-center gap-2"><div class="flex min-h-[44px] min-w-0 flex-1 items-center gap-2.5 rounded-xl px-2 py-1 text-left"><span class="relative shrink-0"><div class="size-8 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-xs font-bold text-primary">${escape_html((user()?.name ?? "A").split(/\s+/).map((n) => n[0]).join("").slice(0, 2).toUpperCase())}</div> <span class="absolute bottom-0 right-0 size-2.5 rounded-full border-2 border-card bg-emerald-500" aria-hidden="true" title="Session active"></span></span> <span class="min-w-0 flex-1"><span class="block truncate text-xs font-semibold text-foreground">${escape_html(user()?.name ?? "Applicant")}</span> <span class="block truncate text-[11px] text-muted-foreground">Portal Account</span></span></div> <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Sign out" aria-label="Sign out">`);
      Log_out($$renderer3, { class: "size-4", "aria-hidden": "true" });
      $$renderer3.push(`<!----></button></div></div></aside> <div class="flex min-w-0 flex-1 flex-col md:pl-60 relative print:pl-0"><header class="sticky top-0 z-30 shrink-0 h-16 glass-panel border-b border-border/80 flex items-center justify-between gap-2 px-4 sm:px-6 lg:px-8 relative print:hidden"><div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">`);
      Button($$renderer3, {
        variant: "ghost",
        size: "icon",
        class: "md:hidden shrink-0",
        onclick: () => sidebarOpen = !sidebarOpen,
        "aria-label": "Open menu",
        children: ($$renderer4) => {
          Menu($$renderer4, { class: "h-5 w-5" });
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: "/applicant/portal",
        class: "flex items-center gap-2 md:hidden text-base font-bold tracking-tight text-foreground",
        children: ($$renderer4) => {
          $$renderer4.push(`<span class="flex size-7 items-center justify-center rounded-lg bg-primary text-primary-foreground text-xs font-extrabold shadow-xs" aria-hidden="true">N</span> <span>NEXIAM</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (breadcrumbs.length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<nav class="hidden min-w-0 flex-1 items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs sm:text-sm scrollbar-hide md:flex" aria-label="Breadcrumb">`);
        Link($$renderer3, {
          href: "/applicant/portal",
          class: "text-muted-foreground hover:text-foreground transition-colors font-medium shrink-0",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Home`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <!--[-->`);
        const each_array_1 = ensure_array_like(breadcrumbs);
        for (let i = 0, $$length = each_array_1.length; i < $$length; i++) {
          let crumb = each_array_1[i];
          Chevron_right($$renderer3, {
            class: "size-3.5 text-muted-foreground/50 shrink-0",
            "aria-hidden": "true"
          });
          $$renderer3.push(`<!----> `);
          if (crumb.href && i < breadcrumbs.length - 1) {
            $$renderer3.push("<!--[0-->");
            Link($$renderer3, {
              href: crumb.href,
              class: "text-muted-foreground hover:text-foreground transition-colors font-medium shrink-0",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(crumb.label)}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<span class="font-semibold text-foreground truncate shrink-0" aria-current="page">${escape_html(crumb.label)}</span>`);
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--></nav>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<span class="hidden md:inline-block text-sm font-semibold tracking-tight text-foreground truncate">${escape_html(pageTitle())}</span>`);
      }
      $$renderer3.push(`<!--]--></div> <div class="flex shrink-0 items-center gap-2 sm:gap-3">`);
      NotificationBell($$renderer3);
      $$renderer3.push(`<!----> `);
      PreferencesMenu($$renderer3);
      $$renderer3.push(`<!----> `);
      Button($$renderer3, {
        type: "button",
        variant: "outline",
        size: "sm",
        class: "hidden sm:inline-flex min-h-9 md:hidden",
        onclick: logout,
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Sign out`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div></header> <main${attr_class(`w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 pb-24 md:pb-12 ${wide ? "max-w-5xl" : "max-w-4xl"}`)}>`);
      children?.($$renderer3);
      $$renderer3.push(`<!----></main></div> <nav aria-label="Applicant mobile quick bar" class="fixed inset-x-0 bottom-0 z-40 w-full border-t border-border/80 bg-card/95 backdrop-blur-md pb-[env(safe-area-inset-bottom)] md:hidden"><div class="relative mx-auto grid h-16 max-w-md grid-cols-3 items-center px-4"><div class="flex justify-start">`);
      Link($$renderer3, {
        href: "/applicant/portal",
        class: `flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition-colors ${currentPath() === "/applicant/portal" ? "text-primary font-semibold" : "text-muted-foreground hover:text-foreground"}`,
        onclick: closeSidebar,
        children: ($$renderer4) => {
          Compass($$renderer4, { class: "size-5", "aria-hidden": "true" });
          $$renderer4.push(`<!----> <span>Status</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> <div class="flex flex-col items-center justify-center"><button type="button" aria-label="Open my applicant QR pass" class="-mt-5 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">`);
      Qr_code($$renderer3, { class: "size-6", "aria-hidden": "true" });
      $$renderer3.push(`<!----></button> <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">My QR</span></div> <div class="flex justify-end">`);
      Link($$renderer3, {
        href: applicationHref(),
        class: `flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition-colors ${currentPath().startsWith("/applicant/applications") ? "text-primary font-semibold" : "text-muted-foreground hover:text-foreground"}`,
        onclick: closeSidebar,
        children: ($$renderer4) => {
          File_text($$renderer4, { class: "size-5", "aria-hidden": "true" });
          $$renderer4.push(`<!----> <span>My App</span>`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div></div></nav> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return qrModalOpen;
          },
          set open($$value) {
            qrModalOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "max-w-md overflow-hidden p-6 sm:p-7",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      class: "space-y-1.5 pb-2",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2">`);
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            class: "text-xl font-bold tracking-tight text-foreground",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->My NEXIAM QR`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        TourTriggerGlyph($$renderer6, {
                          tourKey: "applicant-qr",
                          label: "Need tips on using your QR pass? Ask Kuya Xiam!",
                          onclick: () => qrTourOpen = true
                        });
                        $$renderer6.push(`<!----></div></div> `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            class: "text-xs text-muted-foreground",
                            children: ($$renderer7) => {
                              if (qrData()?.attempt_id) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`Attempt #${escape_html(qrData().attempt_id)} `);
                                if (qrData()?.reference_code) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`· Ref ${escape_html(qrData().reference_code)}`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]-->`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                                $$renderer7.push(`Permanent universal applicant locator`);
                              }
                              $$renderer7.push(`<!--]-->`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (qrData()?.qr) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="flex flex-col items-center gap-4 py-3"><div class="rounded-2xl border-2 border-border/80 bg-white p-3.5 shadow-sm"><canvas class="block size-52 sm:size-56" role="img" aria-label="Your applicant QR code"></canvas></div> <div class="space-y-1 text-center px-2"><p class="text-sm font-semibold text-foreground">Show this QR when asked by NEXIAM staff</p> <p class="text-xs text-muted-foreground">One permanent code for your admission attempt — never changes or expires.</p></div> `);
                    Button($$renderer5, {
                      variant: "outline",
                      class: "w-full min-h-11 gap-2 text-sm font-medium",
                      onclick: () => window.print(),
                      children: ($$renderer6) => {
                        Printer($$renderer6, { class: "size-4", "aria-hidden": "true" });
                        $$renderer6.push(`<!----> <span>Print approved copy</span>`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<div class="rounded-2xl border border-border/70 bg-muted/20 p-6 text-center space-y-2 my-3">`);
                    Qr_code($$renderer5, { class: "mx-auto size-12 text-muted-foreground/40" });
                    $$renderer5.push(`<!----> <p class="text-sm font-semibold text-foreground">QR Pass Pending</p> <p class="text-xs text-muted-foreground">Your official applicant QR pass will be active once your admission attempt is accepted by the Registrar.</p></div>`);
                  }
                  $$renderer5.push(`<!--]--> <div class="rounded-xl border border-border/60 bg-muted/30 p-3.5 text-xs text-muted-foreground space-y-1.5"><p class="font-medium text-foreground">About this QR pass:</p> <ul class="list-disc space-y-1 pl-4"><li>Identifies your admission attempt across all testing desks.</li> <li>Valid at the consultation front desk, registrar counter, and exam entry.</li> <li>Printing is recommended; the printed copy works identically.</li></ul></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      class: "pt-2",
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          type: "button",
                          variant: "ghost",
                          class: "w-full",
                          onclick: () => qrModalOpen = false,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Close`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
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
      $$renderer3.push(` `);
      XiamNpcDialog($$renderer3, {
        tourKey: "applicant-qr",
        context: qrWalkthroughContext(),
        get open() {
          return qrTourOpen;
        },
        set open($$value) {
          qrTourOpen = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div>`);
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
  ApplicantLayout as default
};
