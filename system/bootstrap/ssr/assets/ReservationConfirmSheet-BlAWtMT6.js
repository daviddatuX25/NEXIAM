import { a as ensure_array_like, e as escape_html, c as attr_class, b as attr, B as Button } from "../app.js";
import { C as Calendar_days } from "./calendar-days-CnJjYUSZ.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
function ReservationSlotList($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { groups = [], onSelect = () => {
    } } = $$props;
    $$renderer2.push(`<div class="space-y-4"><!--[-->`);
    const each_array = ensure_array_like(groups);
    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
      let group = each_array[$$index_1];
      if (group.label) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex items-baseline gap-2"><span class="text-2xl font-semibold tabular-nums">${escape_html(group.label)}</span> `);
        if (group.caption) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div><p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">${escape_html(group.caption)}</p></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <ul class="space-y-2"><!--[-->`);
      const each_array_1 = ensure_array_like(group.slots);
      for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
        let slot = each_array_1[$$index];
        $$renderer2.push(`<li><button type="button"${attr_class(`flex w-full items-start gap-3 rounded-lg border px-3 py-3 text-left text-sm transition ${slot.full ? "cursor-not-allowed border-border/50 bg-muted/40 text-muted-foreground" : "border-border hover:border-primary/50 hover:bg-muted/20"}`)}${attr("disabled", slot.full, true)}>`);
        Calendar_days($$renderer2, { class: "mt-0.5 size-4 shrink-0 text-muted-foreground" });
        $$renderer2.push(`<!----> <span class="min-w-0 flex-1 space-y-0.5"><span class="block font-medium">${escape_html(slot.timeLabel)}</span> <span class="block text-xs text-muted-foreground">${escape_html(slot.venue ?? "Room TBD")}`);
        if (slot.code) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`· ${escape_html(slot.code)}`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></span> <span class="block text-xs">`);
        if (slot.full) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`Full`);
        } else if (slot.remaining !== null && slot.capacity !== null) {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`${escape_html(slot.remaining)} of ${escape_html(slot.capacity)} seats left`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`Seats available`);
        }
        $$renderer2.push(`<!--]--></span></span></button></li>`);
      }
      $$renderer2.push(`<!--]--></ul>`);
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function ReservationConfirmSheet($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      title = "Confirm reservation",
      description = "",
      rows = [],
      confirmLabel = "Confirm",
      busyLabel = "Booking…",
      busy = false,
      onConfirm = () => {
      },
      onClose = () => {
      }
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return open;
          },
          set open($$value) {
            open = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(title)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (description) {
                          $$renderer6.push("<!--[0-->");
                          if (Dialog_description) {
                            $$renderer6.push("<!--[-->");
                            Dialog_description($$renderer6, {
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(description)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (rows.length > 0) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<dl class="space-y-1 text-sm"><!--[-->`);
                    const each_array = ensure_array_like(rows);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let row = each_array[$$index];
                      $$renderer5.push(`<div class="flex justify-between gap-4"><dt class="text-muted-foreground">${escape_html(row.label)}</dt> <dd>${escape_html(row.value)}</dd></div>`);
                    }
                    $$renderer5.push(`<!--]--></dl>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          type: "button",
                          variant: "outline",
                          class: "min-h-11",
                          disabled: busy,
                          onclick: onClose,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Back`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          type: "button",
                          class: "min-h-11",
                          disabled: busy,
                          onclick: onConfirm,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(busy ? busyLabel : confirmLabel)}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
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
  ReservationSlotList as R,
  ReservationConfirmSheet as a
};
