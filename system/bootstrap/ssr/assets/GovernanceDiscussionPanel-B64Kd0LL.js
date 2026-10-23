import { b as attr, e as escape_html, B as Button, a as ensure_array_like, d as derived, n as newIdempotencyKey } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { g as governanceRequest, a as governanceRoute, b as governanceError, G as GovernanceRequestError } from "./governanceRequests-C1KnF59a.js";
function GovernanceDiscussionPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      program,
      cycle,
      topic,
      initialList = null,
      onGovernanceActivity = () => {
      }
    } = $$props;
    let list = null;
    let transcript = null;
    let messages = [];
    let search = "";
    let subject = "";
    let body = "";
    let error = "";
    let busy = false;
    let sequence = 0;
    let pending = null;
    const scope = derived(() => `${program?.id}:${cycle?.id}:${topic}`);
    async function loadList(page = 1) {
      const token = ++sequence;
      busy = true;
      error = "";
      try {
        const query = new URLSearchParams({
          discussion_page: String(page),
          topic,
          discussion_search: search
        });
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.governance.show", { program: program.id, cycle: cycle.id })}?${query}`);
        if (token !== sequence) return;
        list = data.discussions?.[topic] ?? null;
        if (!list) error = "Discussion access is not available for this topic.";
      } catch (failure) {
        if (token === sequence) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) {
            list = null;
            transcript = null;
            messages = [];
          }
        }
      } finally {
        if (token === sequence) busy = false;
      }
    }
    async function openHistory(thread, older = false) {
      const token = ++sequence;
      busy = true;
      error = "";
      const cursor = older ? transcript?.pagination?.next_before_message_id : null;
      try {
        const query = new URLSearchParams({ thread_id: String(thread.id), per_page: "30" });
        if (cursor) query.set("before_message_id", String(cursor));
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.discussions.history", { program: program.id, cycle: cycle.id })}?${query}`);
        if (token !== sequence) return;
        transcript = data;
        messages = older ? [
          ...data.messages,
          ...messages.filter((message) => !data.messages.some((old) => old.id === message.id))
        ] : data.messages;
      } catch (failure) {
        if (token === sequence) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) {
            transcript = null;
            messages = [];
          }
        }
      } finally {
        if (token === sequence) busy = false;
      }
    }
    async function command(action, payload) {
      if (busy) return;
      const capturedScope = scope();
      const fingerprint = JSON.stringify({ action, payload, scope: scope() });
      if (pending?.fingerprint !== fingerprint) pending = { fingerprint, key: newIdempotencyKey() };
      busy = true;
      error = "";
      try {
        const receipt = await governanceRequest(governanceRoute(`setup.admission-support.discussions.${action}`, { program: program.id, cycle: cycle.id }), { ...payload, idempotency_key: pending.key });
        if (scope() !== capturedScope) return;
        pending = null;
        if (action === "read" || action === "send") {
          onGovernanceActivity({
            type: action === "read" ? "discussion.read" : "discussion.message-sent",
            threadId: Number(payload.thread_id)
          });
        }
        busy = false;
        if (action === "open") {
          subject = "";
          await loadList(1);
          if (scope() !== capturedScope) return;
          await openHistory(receipt.thread);
        } else {
          if (action === "send") body = "";
          const activeThread = transcript.thread;
          await loadList(list?.pagination?.current_page ?? 1);
          if (scope() !== capturedScope) return;
          await openHistory(activeThread);
        }
      } catch (failure) {
        if (scope() === capturedScope) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) {
            list = null;
            transcript = null;
            messages = [];
          }
        }
      } finally {
        if (scope() === capturedScope) busy = false;
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<section class="min-w-0 space-y-4"${attr("aria-label", `${topic} discussions`)}${attr("aria-busy", busy)}><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold capitalize">${escape_html(topic)} discussion</h3> `);
      Button($$renderer3, {
        variant: "ghost",
        size: "sm",
        disabled: busy,
        onclick: () => transcript ? openHistory(transcript.thread) : loadList(list?.pagination?.current_page ?? 1),
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Refresh`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></div> <p class="text-xs text-muted-foreground">Staff-only, ${escape_html(program?.code)} · ${escape_html(cycle?.staff_label ?? cycle?.name)}. Do not include applicant details.</p> `);
      if (error) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(error)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (transcript) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="flex flex-wrap items-center justify-between gap-2">`);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          disabled: busy,
          onclick: () => {
            transcript = null;
            messages = [];
            loadList(list?.pagination?.current_page ?? 1);
          },
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to discussions`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <span class="text-xs text-muted-foreground">${escape_html(transcript.thread.status.replace("_", " "))} · ${escape_html(transcript.thread.unread && transcript.thread.message_count === 0 ? "New discussion" : `${transcript.unread_count} unread`)}</span></div> <h4 class="font-medium break-words">${escape_html(transcript.thread.subject)}</h4> `);
        if (transcript.pagination?.has_more) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            disabled: busy,
            onclick: () => openHistory(transcript.thread, true),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Load older messages`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <ol class="max-h-[28rem] space-y-4 overflow-y-auto" aria-label="Messages">`);
        const each_array = ensure_array_like(messages);
        if (each_array.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let message = each_array[$$index];
            $$renderer3.push(`<li class="space-y-1 border-b pb-3"><p class="text-xs text-muted-foreground"><span class="font-medium text-foreground">${escape_html(message.sender?.name ?? "Staff")}</span> · ${escape_html(new Date(message.created_at).toLocaleString())}</p><p class="whitespace-pre-wrap break-words text-sm">${escape_html(message.body)}</p></li>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<li class="text-sm text-muted-foreground">No messages yet.</li>`);
        }
        $$renderer3.push(`<!--]--></ol> `);
        if (transcript.can_read && transcript.thread.unread) {
          $$renderer3.push("<!--[0-->");
          if (messages.length) {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              variant: "outline",
              size: "sm",
              disabled: busy,
              onclick: () => command("read", {
                thread_id: transcript.thread.id,
                through_message_id: messages[messages.length - 1].id
              }),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Mark displayed messages read`);
              },
              $$slots: { default: true }
            });
          } else if (transcript.thread.message_count === 0) {
            $$renderer3.push("<!--[1-->");
            Button($$renderer3, {
              variant: "outline",
              size: "sm",
              disabled: busy,
              onclick: () => command("read", { thread_id: transcript.thread.id, through_message_id: null }),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Acknowledge empty discussion`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (transcript.can_manage_state) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap gap-2" aria-label="Discussion state"><!--[-->`);
          const each_array_1 = ensure_array_like(["open", "read_only", "closed"]);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let status = each_array_1[$$index_1];
            Button($$renderer3, {
              variant: "outline",
              size: "sm",
              disabled: busy || transcript.thread.status === status,
              onclick: () => command("state", { thread_id: transcript.thread.id, status }),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(status === "open" ? "Reopen" : status === "closed" ? "Close discussion" : "Make read-only")}`);
              },
              $$slots: { default: true }
            });
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (transcript.can_send) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<form class="space-y-2"><label${attr("for", `reply-${scope()}`)} class="text-sm font-medium">Reply</label> `);
          Textarea($$renderer3, {
            id: `reply-${scope()}`,
            required: true,
            maxlength: 8e3,
            rows: 4,
            disabled: busy,
            get value() {
              return body;
            },
            set value($$value) {
              body = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "submit",
            disabled: busy || !body.trim(),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(busy ? "Sending…" : "Send reply")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></form>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">This discussion is readable; replies are unavailable.</p>`);
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
        if (busy) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="status" class="text-sm text-muted-foreground">Loading discussions…</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <form class="flex flex-col gap-2 sm:flex-row"><label class="sr-only"${attr("for", `search-${scope()}`)}>Search discussion subjects</label> `);
        Input($$renderer3, {
          id: `search-${scope()}`,
          maxlength: 255,
          placeholder: "Search subjects",
          disabled: busy,
          get value() {
            return search;
          },
          set value($$value) {
            search = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          type: "submit",
          variant: "outline",
          disabled: busy,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Search`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></form> <ul class="divide-y">`);
        const each_array_2 = ensure_array_like(list?.threads ?? []);
        if (each_array_2.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
            let thread = each_array_2[$$index_2];
            $$renderer3.push(`<li><button type="button" class="w-full py-3 text-left hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"${attr("disabled", busy || !thread.can_read, true)}><span class="flex min-w-0 items-center justify-between gap-3"><span class="min-w-0 break-words text-sm font-medium">${escape_html(thread.subject)}</span> `);
            if (thread.unread) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">${escape_html(thread.unread_count || "New")}</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></span> <span class="block text-xs text-muted-foreground">${escape_html(thread.status.replace("_", " "))} · ${escape_html(thread.message_count)} ${escape_html(thread.message_count === 1 ? "message" : "messages")}</span></button></li>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<li class="py-3 text-sm text-muted-foreground">No discussions in this context.</li>`);
        }
        $$renderer3.push(`<!--]--></ul> `);
        if (list?.pagination && (list.pagination.current_page > 1 || list.pagination.has_more)) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex items-center gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            disabled: busy || list.pagination.current_page <= 1,
            onclick: () => loadList(list.pagination.current_page - 1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Previous`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----><span class="text-xs">Page ${escape_html(list.pagination.current_page)}</span>`);
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            disabled: busy || !list.pagination.has_more,
            onclick: () => loadList(list.pagination.current_page + 1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Next`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (list?.can_send) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<form class="space-y-2 border-t pt-4"><label${attr("for", `subject-${scope()}`)} class="text-sm font-medium">New discussion subject</label> `);
          Input($$renderer3, {
            id: `subject-${scope()}`,
            required: true,
            maxlength: 255,
            disabled: busy,
            get value() {
              return subject;
            },
            set value($$value) {
              subject = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "submit",
            disabled: busy || !subject.trim(),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(busy ? "Opening…" : "Start discussion")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></form>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
      $$renderer3.push(`<!--]--></section>`);
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
  GovernanceDiscussionPanel as G
};
