import { g as sanitize_props, i as spread_props, j as slot, h as head, B as Button, e as escape_html, a as ensure_array_like } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { V as Video } from "./video-D4mycEAq.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { U as User_minus } from "./user-minus-CPyEYZhK.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function Mic($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 19v3" }],
    ["path", { "d": "M19 10v2a7 7 0 0 1-14 0v-2" }],
    [
      "rect",
      { "x": "9", "y": "2", "width": "6", "height": "13", "rx": "3" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "mic" },
    $$sanitized_props,
    {
      /**
       * @component @name Mic
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMTl2MyIgLz4KICA8cGF0aCBkPSJNMTkgMTB2MmE3IDcgMCAwIDEtMTQgMHYtMiIgLz4KICA8cmVjdCB4PSI5IiB5PSIyIiB3aWR0aD0iNiIgaGVpZ2h0PSIxMyIgcng9IjMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/mic
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
function Phone_off($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M10.1 13.9a14 14 0 0 0 3.732 2.668 1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2 18 18 0 0 1-12.728-5.272"
      }
    ],
    ["path", { "d": "M22 2 2 22" }],
    [
      "path",
      {
        "d": "M4.76 13.582A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 .244.473"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "phone-off" },
    $$sanitized_props,
    {
      /**
       * @component @name PhoneOff
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuMSAxMy45YTE0IDE0IDAgMCAwIDMuNzMyIDIuNjY4IDEgMSAwIDAgMCAxLjIxMy0uMzAzbC4zNTUtLjQ2NUEyIDIgMCAwIDEgMTcgMTVoM2EyIDIgMCAwIDEgMiAydjNhMiAyIDAgMCAxLTIgMiAxOCAxOCAwIDAgMS0xMi43MjgtNS4yNzIiIC8+CiAgPHBhdGggZD0iTTIyIDIgMiAyMiIgLz4KICA8cGF0aCBkPSJNNC43NiAxMy41ODJBMTggMTggMCAwIDEgMiA0YTIgMiAwIDAgMSAyLTJoM2EyIDIgMCAwIDEgMiAydjNhMiAyIDAgMCAxLS44IDEuNmwtLjQ2OC4zNTFhMSAxIDAgMCAwLS4yOTIgMS4yMzMgMTQgMTQgMCAwIDAgLjI0NC40NzMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/phone-off
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
function OnlineEncounter($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      encounter,
      applicant,
      participant_name = null,
      score_summary = null,
      can_control = false,
      decision_url = "/consultation",
      back_url = "/consultation"
    } = $$props;
    let api = null;
    let callError = "";
    let closing = false;
    let participantId = "";
    let participantOptions = [];
    function csrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
    }
    async function postJson(url) {
      const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken(),
          "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify({})
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.message ?? "The call action could not be completed.");
      }
      return payload;
    }
    function admitParticipant() {
    }
    function muteParticipants() {
      if (!api) return;
      api.executeCommand("muteEveryone");
    }
    function removeParticipant() {
      if (api && participantId) {
        api.executeCommand("kickParticipant", participantId);
        participantId = "";
      }
    }
    async function endConference() {
      closing = true;
      callError = "";
      try {
        await postJson(`/consultation/encounters/${encounter.id}/close`);
        api?.dispose();
        api = null;
        window.location.assign(back_url);
      } catch (error) {
        callError = error instanceof Error ? error.message : "The call could not be ended.";
        closing = false;
      }
    }
    head("5rjz93", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Online consultation · NEXIAM</title>`);
      });
    });
    $$renderer2.push(`<main class="min-h-screen bg-background p-3 sm:p-6"><div class="mx-auto flex max-w-[1600px] flex-col gap-4"><header class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-3">`);
    Link($$renderer2, {
      href: back_url,
      "aria-label": "Back to consultations",
      children: ($$renderer3) => {
        Button($$renderer3, {
          variant: "ghost",
          size: "icon",
          class: "size-10",
          children: ($$renderer4) => {
            Arrow_left($$renderer4, { class: "size-4" });
          },
          $$slots: { default: true }
        });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <div><h1 class="text-xl font-semibold">Online consultation</h1> <p class="text-sm text-muted-foreground">${escape_html(applicant.name)} · ${escape_html(encounter.status_label ?? "Online appointment")}</p></div></div> `);
    Badge($$renderer2, {
      variant: "outline",
      class: "border-sky-500/30 bg-sky-500/5 text-sky-700 dark:text-sky-300",
      children: ($$renderer3) => {
        Video($$renderer3, { class: "mr-1 size-3.5" });
        $$renderer3.push(`<!----> Online consultation`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></header> `);
    if (callError) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div role="alert" class="flex items-center gap-2 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">`);
      Circle_alert($$renderer2, { class: "size-4 shrink-0" });
      $$renderer2.push(`<!----> ${escape_html(callError)}</div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="grid min-h-[70vh] gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]"><section class="relative min-h-[55vh] overflow-hidden rounded-xl border bg-black" aria-label="Consultation video call"><div class="absolute inset-0"></div> `);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute inset-0 grid place-items-center bg-black/70 text-center text-sm text-white"><div class="space-y-2">`);
      Video($$renderer2, { class: "mx-auto size-8" });
      $$renderer2.push(`<!----><p>Connecting to the secure consultation room…</p></div></div>`);
    }
    $$renderer2.push(`<!--]--></section> <aside class="space-y-4 rounded-xl border bg-card p-4 sm:p-5"><section class="space-y-1"><h2 class="text-base font-semibold">Applicant overview</h2> <p class="text-sm text-muted-foreground">${escape_html(applicant.name)}</p> `);
    if (applicant.reference_code) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-xs text-muted-foreground">Reference: ${escape_html(applicant.reference_code)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section> `);
    if (score_summary?.series?.length) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="space-y-3 border-t pt-4"><h3 class="text-sm font-semibold">Candidate score summary</h3> <ul class="space-y-2"><!--[-->`);
      const each_array = ensure_array_like(score_summary.series);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let item = each_array[$$index];
        $$renderer2.push(`<li class="flex items-center justify-between gap-3 text-sm"><span class="text-muted-foreground">${escape_html(item.area_name ?? item.area_code)}</span> <span class="font-medium tabular-nums">${escape_html(item.score ?? "—")}</span></li>`);
      }
      $$renderer2.push(`<!--]--></ul></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="border-t pt-4 text-sm text-muted-foreground">No finalized score summary is available for this appointment.</p>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (can_control) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="space-y-3 border-t pt-4"><h3 class="text-sm font-semibold">Counselor controls</h3> `);
      Button($$renderer2, {
        variant: "outline",
        class: "w-full justify-start gap-2",
        onclick: admitParticipant,
        disabled: !api || true,
        children: ($$renderer3) => {
          User_check($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> ${escape_html("Waiting for applicant")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> `);
      Button($$renderer2, {
        variant: "outline",
        class: "w-full justify-start gap-2",
        onclick: muteParticipants,
        disabled: !api,
        children: ($$renderer3) => {
          Mic($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> Mute participants`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> <div class="flex gap-2">`);
      $$renderer2.select(
        {
          value: participantId,
          class: "h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm",
          "aria-label": "Participant to remove"
        },
        ($$renderer3) => {
          $$renderer3.option({ value: "" }, ($$renderer4) => {
            $$renderer4.push(`Select participant`);
          });
          $$renderer3.push(`<!--[-->`);
          const each_array_1 = ensure_array_like(participantOptions);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let participant = each_array_1[$$index_1];
            $$renderer3.option({ value: participant.id }, ($$renderer4) => {
              $$renderer4.push(`${escape_html(participant.name)}`);
            });
          }
          $$renderer3.push(`<!--]-->`);
        }
      );
      $$renderer2.push(` `);
      Button($$renderer2, {
        variant: "outline",
        size: "icon",
        "aria-label": "Remove participant",
        onclick: removeParticipant,
        disabled: !participantId || !api,
        children: ($$renderer3) => {
          User_minus($$renderer3, { class: "size-4" });
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> `);
      Button($$renderer2, {
        variant: "destructive",
        class: "w-full gap-2",
        onclick: endConference,
        disabled: closing,
        children: ($$renderer3) => {
          Phone_off($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> ${escape_html(closing ? "Ending call…" : "End Conference")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></section> `);
      Button($$renderer2, {
        href: decision_url,
        target: "_blank",
        rel: "noopener noreferrer",
        class: "w-full",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Open consultation decision in new tab`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></aside></div></div></main>`);
  });
}
export {
  OnlineEncounter as default
};
