import { h as head, e as escape_html, a as ensure_array_like, B as Button, c as attr_class, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { G as Grid_3x3 } from "./grid-3x3-D2MHQ08_.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
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
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { room, enable_seating_arrangements = true } = $$props;
    let defaultId = String(room.default_seating_arrangement?.id ?? room.default_seating_arrangement_id ?? "");
    const arrangements = derived(() => room.seating_arrangements ?? room.applicable_seating_arrangements ?? []);
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Rooms & seating", href: "/setup/rooms" },
      { label: room.name }
    ];
    function label(pattern) {
      return {
        uniform: "Uniform A",
        checkerboard: "Checkerboard A/B",
        latin_four: "Latin four A–D",
        linear_alternating: "Linear alternating A/B"
      }[pattern] ?? pattern ?? "—";
    }
    function roomArrangementName(arrangement) {
      return arrangement.name ?? arrangement.label ?? `Arrangement ${arrangement.id}`;
    }
    function spaceKindLabel(spaceKind) {
      return {
        room: "Exam Room",
        assembly_venue: "Assembly Venue",
        service_point: "Service Window"
      }[spaceKind] ?? "Room";
    }
    function toggleRoom() {
      router.post(`/setup/rooms/${room.id}/${room.is_active ? "deactivate" : "activate"}`);
    }
    head("bw6vjj", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(room.name)} · Rooms &amp; seating</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            $$renderer4.push(`<div class="flex flex-wrap gap-2">`);
            Button($$renderer4, {
              variant: "outline",
              onclick: toggleRoom,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(room.is_active ? "Deactivate" : "Activate")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Link($$renderer4, {
              href: `/setup/rooms/${room.id}/edit`,
              children: ($$renderer5) => {
                Button($$renderer5, {
                  variant: "outline",
                  class: "gap-2",
                  children: ($$renderer6) => {
                    Pencil($$renderer6, { class: "h-4 w-4" });
                    $$renderer6.push(`<!---->Edit room`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (enable_seating_arrangements) {
              $$renderer4.push("<!--[0-->");
              Link($$renderer4, {
                href: "/setup/seating-arrangements/create",
                children: ($$renderer5) => {
                  Button($$renderer5, {
                    class: "gap-2",
                    children: ($$renderer6) => {
                      Plus($$renderer6, { class: "h-4 w-4" });
                      $$renderer6.push(`<!---->Create arrangement`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          };
          PageHeaderBar($$renderer3, {
            title: room.name,
            description: `${spaceKindLabel(room.space_kind)} · Room ${room.code} · capacity ${room.capacity}`,
            actions
          });
        }
        $$renderer3.push(`<!----> <section class="border-border bg-card rounded-xl border p-5" aria-labelledby="room-status"><div class="flex items-center justify-between gap-4"><div><h2 id="room-status" class="font-semibold">Room status</h2> <p class="text-muted-foreground mt-1 text-sm">Inactive rooms stay in Setup but are unavailable for new scheduling.</p></div> `);
        Badge($$renderer3, {
          variant: room.is_active ? "default" : "secondary",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(room.is_active ? "Active" : "Inactive")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></section> `);
        if (enable_seating_arrangements) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="border-border bg-card rounded-xl border p-5" aria-labelledby="default-arrangement-title"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 id="default-arrangement-title" class="font-semibold">Room default arrangement</h2> <p class="text-muted-foreground mt-1 text-sm">The default is preselected when staff create a session in this room. It must stay active, applicable, and within capacity.</p></div> `);
          Badge($$renderer3, {
            variant: defaultId ? "default" : "secondary",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(defaultId ? "Configured" : "Not configured")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"><div class="min-w-0 flex-1 space-y-1.5"><label for="room-default-arrangement" class="text-sm font-medium">Default seating arrangement</label> `);
          $$renderer3.select(
            {
              id: "room-default-arrangement",
              value: defaultId,
              class: "border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "" }, ($$renderer5) => {
                $$renderer5.push(`No default`);
              });
              $$renderer4.push(`<!--[-->`);
              const each_array = ensure_array_like(arrangements());
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let arrangement = each_array[$$index];
                $$renderer4.option(
                  {
                    value: String(arrangement.id),
                    disabled: arrangement.is_active === false || arrangement.is_compatible === false
                  },
                  ($$renderer5) => {
                    $$renderer5.push(`${escape_html(roomArrangementName(arrangement))} · ${escape_html(arrangement.rows ?? "—")}×${escape_html(arrangement.columns ?? "—")}
                                ${escape_html(arrangement.is_compatible === false ? " · incompatible" : "")}`);
                  }
                );
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
          $$renderer3.push(`</div> `);
          Button($$renderer3, {
            type: "submit",
            class: "min-h-11 gap-2",
            disabled: !room.is_active,
            children: ($$renderer4) => {
              Save($$renderer4, { class: "h-4 w-4" });
              $$renderer4.push(`<!---->${escape_html("Save default")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></form> `);
          if (!room.is_active) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-muted-foreground mt-3 text-xs">Activate this room before assigning a default.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></section> <section class="space-y-3" aria-labelledby="arrangements-title"><div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h2 id="arrangements-title" class="text-lg font-semibold">Applicable arrangements</h2> <p class="text-muted-foreground text-sm">Reusable definitions available to this room. Session seats are materialized only when a session is published.</p></div> `);
          Link($$renderer3, {
            href: "/setup/seating-arrangements",
            children: ($$renderer4) => {
              Button($$renderer4, {
                variant: "outline",
                class: "min-h-10",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Browse catalog`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> `);
          if (arrangements().length === 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center">`);
            Grid_3x3($$renderer3, { class: "text-muted-foreground mx-auto h-8 w-8" });
            $$renderer3.push(`<!----> <h3 class="mt-4 font-semibold">No applicable arrangements</h3> <p class="text-muted-foreground mt-1 text-sm">Create an all-room arrangement or select this room when creating a restricted arrangement.</p> `);
            Link($$renderer3, {
              href: "/setup/seating-arrangements/create",
              class: "mt-5 inline-flex",
              children: ($$renderer4) => {
                Button($$renderer4, {
                  class: "min-h-11 gap-2",
                  children: ($$renderer5) => {
                    Plus($$renderer5, { class: "h-4 w-4" });
                    $$renderer5.push(`<!---->Create arrangement`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<ul class="grid gap-3 lg:grid-cols-2" role="list"><!--[-->`);
            const each_array_1 = ensure_array_like(arrangements());
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let arrangement = each_array_1[$$index_1];
              $$renderer3.push(`<li${attr_class(`border-border bg-card rounded-xl border p-5 ${arrangement.is_active === false ? "opacity-70" : ""}`)}><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-semibold">${escape_html(roomArrangementName(arrangement))}</h3> <p class="text-muted-foreground mt-1 text-sm">${escape_html(arrangement.rows ?? "—")} rows × ${escape_html(arrangement.columns ?? "—")} columns
                                        · ${escape_html(arrangement.desk_count ?? (arrangement.rows ?? 0) * (arrangement.columns ?? 0))} seats</p></div> `);
              Badge($$renderer3, {
                variant: arrangement.is_active === false ? "secondary" : "outline",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(arrangement.is_active === false ? "Inactive" : "Available")}`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></div> <div class="mt-4 flex items-center justify-between gap-3"><p class="text-sm"><span class="text-muted-foreground">Pattern:</span> ${escape_html(label(arrangement.booklet_pattern))}</p> `);
              Link($$renderer3, {
                href: `/setup/seating-arrangements/${arrangement.id}/edit`,
                children: ($$renderer4) => {
                  Button($$renderer4, {
                    size: "sm",
                    variant: "outline",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Edit arrangement`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></div></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          }
          $$renderer3.push(`<!--]--></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Show as default
};
