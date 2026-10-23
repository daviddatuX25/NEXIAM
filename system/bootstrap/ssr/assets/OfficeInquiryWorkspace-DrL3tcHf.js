import { r as run, e as escape_html, B as Button, a as ensure_array_like, b as attr, c as attr_class, d as derived, n as newIdempotencyKey, t as tick, m as csrfHeaders, o as onDestroy } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { router } from "@inertiajs/core";
function caseConversationDraftKey(principalContext, attemptContext, threadId) {
  const principal = principalContext === null || principalContext === void 0 ? `unknown-principal:${threadId}` : String(principalContext);
  const attempt = attemptContext === null || attemptContext === void 0 ? `unknown-attempt:${threadId}` : String(attemptContext);
  return `${principal}\0${attempt}\0${threadId}`;
}
function capturePendingCaseMessage(body, key) {
  const capturedBody = body.trim();
  return capturedBody.length > 0 ? { body: capturedBody, idempotencyKey: key } : null;
}
function mergeCaseMessages(existing, incoming) {
  const byId = /* @__PURE__ */ new Map();
  for (const message of existing) {
    byId.set(message.id, message);
  }
  for (const message of incoming) {
    if (!byId.has(message.id)) {
      byId.set(message.id, message);
    }
  }
  return Array.from(byId.values()).sort((left, right) => {
    const leftSequence = BigInt(left.sequence);
    const rightSequence = BigInt(right.sequence);
    return leftSequence < rightSequence ? -1 : leftSequence > rightSequence ? 1 : 0;
  });
}
function caseConversationBackoffMs(failures, retryAfter) {
  const failureIndex = Number.isFinite(failures) ? Math.max(1, Math.floor(failures)) : 1;
  const backoff = Math.min(
    6e4,
    1e4 * 2 ** Math.min(failureIndex - 1, 3)
  );
  const validRetryAfter = typeof retryAfter === "number" && Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 0;
  return Math.max(backoff, validRetryAfter);
}
function CaseConversation($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    class CaseConversationRequestFailure extends Error {
      kind;
      constructor(kind) {
        super(kind);
        this.name = "CaseConversationRequestFailure";
        this.kind = kind;
      }
    }
    let {
      initial,
      snapshotUrl,
      sendUrl,
      readUrl,
      principalContext = null,
      attemptContext = null
    } = $$props;
    const capturedInitial = run(() => initial);
    const REQUEST_TIMEOUT_MS = 1e4;
    const MAX_RENDERED_MESSAGES = 500;
    let conversation = capturedInitial;
    let messages = capturedInitial.messages;
    let draft = "";
    let pending = null;
    let sendState = "idle";
    let accessState = "available";
    let feedback = "";
    let stale = false;
    let staleCopy = "";
    let olderLoading = false;
    let sendBusy = false;
    let atBottom = true;
    let newMessageCount = 0;
    let abandonRequested = false;
    let retryAvailableAt = 0;
    capturedInitial.observation_token;
    capturedInitial.page.newest_sequence;
    let latestWindowEvicted = false;
    let olderHistoryAvailable = capturedInitial.page.has_older;
    let pollTimer = null;
    let retryTimer = null;
    let snapshotController = null;
    let snapshotBusy = false;
    let sendController = null;
    let readController = null;
    let sendFailures = 0;
    capturedInitial.page.newest_sequence ?? "0";
    let mounted = false;
    let generation = 0;
    const threadDrafts = /* @__PURE__ */ new Map();
    function effectivePrincipal(value, threadId) {
      return value === null || value === void 0 ? `unknown-principal:${threadId}` : String(value);
    }
    function effectiveAttempt(value, threadId) {
      return value === null || value === void 0 ? `unknown-attempt:${threadId}` : String(value);
    }
    function scopeFor(snapshot, principal, attempt, readUrlValue, sendUrlValue, snapshotUrlValue) {
      return `${principal}\0${attempt}\0${snapshot.thread.id}\0${snapshotUrlValue}\0${sendUrlValue}\0${readUrlValue}`;
    }
    let activePrincipal = effectivePrincipal(run(() => principalContext), capturedInitial.thread.id);
    let activeAttempt = effectiveAttempt(run(() => attemptContext), capturedInitial.thread.id);
    scopeFor(capturedInitial, activePrincipal, activeAttempt, run(() => readUrl), run(() => sendUrl), run(() => snapshotUrl));
    caseConversationDraftKey(activePrincipal, activeAttempt, capturedInitial.thread.id);
    let canRead = derived(() => accessState === "available" && conversation?.thread.can_read === true);
    let canSend = derived(() => canRead() && conversation?.thread.status === "open" && conversation?.thread.can_send === true);
    function isCurrent(requestGeneration) {
      return mounted;
    }
    function clearTimer(timer) {
      if (timer !== null) {
        window.clearTimeout(timer);
      }
    }
    function scheduleLongTimer(delayMs, callback, setHandle) {
      const wait = () => {
        {
          setHandle(null);
          return;
        }
      };
      wait();
    }
    function clearScheduledWork() {
      clearTimer(pollTimer);
      clearTimer(retryTimer);
      pollTimer = null;
      retryTimer = null;
      retryAvailableAt = 0;
    }
    function abortRequests() {
      snapshotController?.abort();
      sendController?.abort();
      readController?.abort();
      snapshotController = null;
      sendController = null;
      readController = null;
    }
    function sameOriginUrl(rawUrl) {
      const url = new URL(rawUrl, window.location.href);
      if (url.origin !== window.location.origin) {
        throw new Error("Conversation requests must stay on this origin.");
      }
      return url;
    }
    async function requestJson(url, init, kind, requestGeneration) {
      const target = sameOriginUrl(url);
      const controller = new AbortController();
      let timedOut = false;
      {
        sendController = controller;
      }
      const timeout = window.setTimeout(
        () => {
          timedOut = true;
          controller.abort();
        },
        REQUEST_TIMEOUT_MS
      );
      try {
        const response = await fetch(target.toString(), {
          ...init,
          credentials: "same-origin",
          mode: "same-origin",
          cache: "no-store",
          signal: controller.signal
        });
        const payload = await response.json().catch(() => null);
        return { response, payload };
      } catch {
        if (timedOut) {
          throw new CaseConversationRequestFailure("timeout");
        }
        if (controller.signal.aborted || generation !== requestGeneration) {
          throw new CaseConversationRequestFailure("aborted");
        }
        throw new CaseConversationRequestFailure("network");
      } finally {
        window.clearTimeout(timeout);
        if (sendController === controller) {
          sendController = null;
        } else ;
      }
    }
    function requestHeaders(json = false) {
      const headers = new Headers({
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...json ? { "Content-Type": "application/json" } : {}
      });
      for (const [name, value] of Object.entries(csrfHeaders())) {
        if (typeof value === "string") {
          headers.set(name, value);
        }
      }
      return headers;
    }
    function retryAfterMilliseconds(headers) {
      const value = headers.get("Retry-After")?.trim();
      if (!value) {
        return null;
      }
      if (/^\d+$/.test(value)) {
        const milliseconds = Number(value) * 1e3;
        return Number.isFinite(milliseconds) ? milliseconds : null;
      }
      const timestamp = Date.parse(value);
      if (!Number.isFinite(timestamp)) {
        return null;
      }
      return Math.max(0, timestamp - Date.now());
    }
    function setRetryWait(delayMs) {
      clearTimer(retryTimer);
      retryTimer = null;
      retryAvailableAt = Date.now() + delayMs;
      if (delayMs <= 0) {
        retryAvailableAt = 0;
        return;
      }
      scheduleLongTimer(
        delayMs,
        () => {
          retryAvailableAt = 0;
          retryTimer = null;
        },
        (handle) => {
          retryTimer = handle;
        }
      );
    }
    function isVisible() {
      return typeof document !== "undefined" && document.visibilityState !== "hidden" && !document.hidden;
    }
    function loseSession() {
      generation += 1;
      clearScheduledWork();
      abortRequests();
      clearProtectedState();
      accessState = "session_expired";
      feedback = "Your session has expired. Sign in again to continue. Unsaved protected conversation state has been cleared.";
    }
    function loseAccess() {
      generation += 1;
      clearScheduledWork();
      abortRequests();
      clearProtectedState();
      accessState = "access_lost";
      feedback = "This conversation is no longer available.";
    }
    function clearProtectedState() {
      threadDrafts.clear();
      snapshotBusy = false;
      sendBusy = false;
      olderLoading = false;
      conversation = null;
      messages = [];
      draft = "";
      pending = null;
      sendState = "idle";
      stale = false;
      staleCopy = "";
      newMessageCount = 0;
      olderHistoryAvailable = false;
      latestWindowEvicted = false;
      abandonRequested = false;
      retryAvailableAt = 0;
    }
    function isMessage(value) {
      if (!value || typeof value !== "object") {
        return false;
      }
      const candidate = value;
      return typeof candidate.id === "string" && typeof candidate.sequence === "string" && typeof candidate.body === "string" && typeof candidate.created_at === "string" && Boolean(candidate.sender && typeof candidate.sender.display_name === "string" && typeof candidate.sender.is_self === "boolean");
    }
    function isSendReceipt(value) {
      if (!value || typeof value !== "object" || !("message" in value)) {
        return false;
      }
      return isMessage(value.message);
    }
    function mergeIncoming(incoming, mode) {
      const unseenCount = incoming.reduce((count, message) => messages.some((existing) => existing.id === message.id) ? count : count + 1, 0);
      const merged = mergeCaseMessages(messages, incoming);
      const prunedCount = Math.max(0, merged.length - MAX_RENDERED_MESSAGES);
      {
        messages = prunedCount > 0 ? merged.slice(prunedCount) : merged;
      }
      if (prunedCount > 0 && conversation) {
        conversation = {
          ...conversation,
          page: { ...conversation.page, has_older: true }
        };
      }
      return unseenCount;
    }
    async function requestCatchup() {
      {
        return;
      }
    }
    async function loadOlder() {
      {
        return;
      }
    }
    async function maybeMarkObservedRead(allowTokenRefresh = true) {
      {
        return;
      }
    }
    function scrollToBottom() {
      {
        return;
      }
    }
    async function jumpToLatest() {
      if (latestWindowEvicted && !await loadLatestWindow()) {
        return;
      }
      if (conversation?.page.has_more_after) {
        await requestCatchup();
      }
      await tick();
      newMessageCount = 0;
      await maybeMarkObservedRead();
    }
    async function loadLatestWindow() {
      {
        return false;
      }
    }
    function parseResponseStatus(status, retryAfter) {
      if (status === 401 || status === 419) {
        loseSession();
        return;
      }
      if (status === 403 || status === 404) {
        loseAccess();
        return;
      }
      if (status === 422) {
        sendState = "validation_error";
        feedback = "This message was not accepted. Keep it unchanged to retry, or explicitly abandon it before editing.";
        setRetryWait(0);
        return;
      }
      if (status === 409) {
        sendState = "conflict";
        feedback = "The send identity conflicts with an existing operation. The message may already exist; retry the same captured message or explicitly abandon it.";
        setRetryWait(0);
        return;
      }
      sendFailures += 1;
      sendState = "uncertain";
      if (status === 429) {
        feedback = "The service is receiving too many requests. This message is still pending; retry will be available after the server’s requested wait.";
        setRetryWait(caseConversationBackoffMs(sendFailures, retryAfter ?? void 0));
      } else {
        feedback = "The message could not be confirmed. It may already exist; retry sends the same captured message safely.";
        setRetryWait(0);
      }
    }
    async function submitMessage() {
      if (!canSend() || sendBusy) {
        return;
      }
      if (!pending) {
        const captured = capturePendingCaseMessage(draft, newIdempotencyKey());
        if (!captured) {
          feedback = "Enter a message before sending.";
          return;
        }
        pending = captured;
      }
      if (Date.now() < retryAvailableAt) {
        feedback = "The server asked us to wait before retrying this message.";
        return;
      }
      await sendPendingMessage();
    }
    async function sendPendingMessage() {
      const captured = pending;
      if (!captured || !canSend() || sendBusy || Date.now() < retryAvailableAt) {
        return;
      }
      const requestGeneration = generation;
      const wasAtBottom = atBottom;
      sendBusy = true;
      sendState = "sending";
      abandonRequested = false;
      feedback = "Sending the captured message…";
      try {
        const { response, payload } = await requestJson(
          sendUrl,
          {
            method: "POST",
            headers: requestHeaders(true),
            body: JSON.stringify({
              body: captured.body,
              idempotency_key: captured.idempotencyKey
            })
          },
          "send",
          requestGeneration
        );
        if (!isCurrent(requestGeneration)) {
          return;
        }
        if (!response.ok) {
          parseResponseStatus(response.status, retryAfterMilliseconds(response.headers));
          return;
        }
        if (!isSendReceipt(payload)) {
          throw new Error("The send response did not include a message receipt.");
        }
        const receiptMessage = payload.message;
        const unseenCount = mergeIncoming([receiptMessage], "delta");
        const latestSequence = BigInt(receiptMessage.sequence) > BigInt(conversation?.latest_sequence ?? "0") ? receiptMessage.sequence : conversation?.latest_sequence ?? receiptMessage.sequence;
        if (conversation) {
          conversation = {
            ...conversation,
            messages,
            latest_sequence: latestSequence,
            page: {
              ...conversation.page,
              oldest_sequence: messages[0]?.sequence ?? null,
              newest_sequence: messages[messages.length - 1]?.sequence ?? null
            }
          };
        }
        if (pending?.idempotencyKey === captured.idempotencyKey) {
          pending = null;
          draft = "";
          sendState = "idle";
          feedback = "Message saved.";
          abandonRequested = false;
          sendFailures = 0;
          setRetryWait(0);
        }
        if (unseenCount > 0 && !wasAtBottom) ;
        await tick();
        if (isCurrent(requestGeneration) && wasAtBottom && atBottom) ;
        if (isCurrent(requestGeneration)) ;
      } catch (failure) {
        {
          return;
        }
      } finally {
        if (generation === requestGeneration) {
          sendBusy = false;
          sendController = null;
        }
      }
    }
    function handleComposerKeydown(event) {
      if (event.key === "Enter" && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        void submitMessage();
      }
    }
    function requestAbandon() {
      if (!pending || sendBusy) {
        return;
      }
      if (!abandonRequested) {
        abandonRequested = true;
        feedback = "The server may already have saved this message. Abandoning stops retries but cannot undo a committed message.";
        return;
      }
      pending = null;
      sendState = "idle";
      abandonRequested = false;
      feedback = "The captured send was abandoned. Review the draft before sending it with a new identity.";
      setRetryWait(0);
    }
    function retryPending() {
      if (!pending || sendBusy || Date.now() < retryAvailableAt) {
        return;
      }
      void sendPendingMessage();
    }
    function messageTime(createdAt) {
      const date = new Date(createdAt);
      return Number.isNaN(date.getTime()) ? createdAt : date.toLocaleString();
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<section class="border-border/70 bg-card/80 min-w-0 space-y-4 rounded-xl border p-3 shadow-sm sm:p-5" aria-label="Case conversation">`);
      if (accessState === "session_expired") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div role="alert" class="border-destructive/30 bg-destructive/5 text-foreground rounded-lg border p-4 text-sm">Your session has expired. Sign in again to continue. Protected
            conversation state has been cleared.</div>`);
      } else if (accessState === "access_lost" || !conversation?.thread.can_read) {
        $$renderer3.push("<!--[1-->");
        $$renderer3.push(`<div role="status" class="border-border bg-muted/40 text-muted-foreground rounded-lg border p-4 text-sm">This conversation is no longer available.</div>`);
      } else if (conversation) {
        $$renderer3.push("<!--[2-->");
        $$renderer3.push(`<header class="border-border/70 flex min-w-0 flex-col gap-3 border-b pb-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0 space-y-1"><h2 class="text-base font-semibold tracking-tight break-words sm:text-lg">${escape_html(conversation.thread.subject)}</h2> <p class="text-muted-foreground text-sm">${escape_html(conversation.thread.thread_type === "internal_ph_guidance" ? "Internal Guidance and Program Head conversation. This thread is not applicant-visible." : conversation.thread.thread_type === "applicant_office_inquiry" ? "Private office inquiry. Only you and authorized staff in that office can read these messages." : "Applicant support conversation. Messages are visible only to eligible participants.")}</p> `);
        if (conversation.thread.program_code) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground text-xs">Program ${escape_html(conversation.thread.program_code)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <span class="border-border bg-background text-muted-foreground inline-flex min-h-8 shrink-0 items-center self-start rounded-full border px-3 text-xs font-medium capitalize">${escape_html(conversation.thread.status.replace("_", " "))}</span></header> <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-muted-foreground text-sm">`);
        if (stale) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span role="status">${escape_html(staleCopy || "Conversation may be out of date. Showing the messages already loaded.")}</span>`);
        } else if (conversation.unread_count > 0) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`${escape_html(conversation.unread_count)} unread ${escape_html(conversation.unread_count === 1 ? "message" : "messages")}`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`Conversation up to date`);
        }
        $$renderer3.push(`<!--]--></p> `);
        Button($$renderer3, {
          variant: "outline",
          class: "min-h-11 min-w-11 px-3",
          disabled: snapshotBusy || !canRead() || !isVisible(),
          onclick: () => void requestCatchup(),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Refresh`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        if (olderHistoryAvailable) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            variant: "outline",
            class: "min-h-11 w-full sm:w-auto",
            disabled: snapshotBusy || olderLoading || !canRead(),
            onclick: () => void loadOlder(),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(olderLoading ? "Loading older messages…" : "Load older messages")}`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="border-border/70 bg-background/70 focus-visible:ring-ring max-h-[min(55vh,32rem)] min-h-40 overflow-y-auto overscroll-contain rounded-lg border p-3 focus-visible:ring-2 focus-visible:outline-none sm:p-4" tabindex="0" role="region" aria-label="Conversation messages"><ol class="space-y-3" aria-live="off">`);
        const each_array = ensure_array_like(messages);
        if (each_array.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let message = each_array[$$index];
            $$renderer3.push(`<li${attr("data-message-id", message.id)}${attr_class(`flex ${message.sender.is_self ? "justify-end" : "justify-start"}`)}><article${attr_class(`max-w-[92%] min-w-0 rounded-lg border px-3 py-2 sm:max-w-[80%] ${message.sender.is_self ? "border-primary/25 bg-primary/10" : "border-border bg-muted/40"}`)}><div class="text-muted-foreground mb-1 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs"><span class="text-foreground font-medium">${escape_html(message.sender.display_name)}${escape_html(message.sender.is_self ? " · You" : "")}</span> <time${attr("datetime", message.created_at)}>${escape_html(messageTime(message.created_at))}</time></div> <p class="text-sm [overflow-wrap:anywhere] break-words whitespace-pre-wrap">${escape_html(message.body)}</p></article></li>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<li class="border-border text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">${escape_html(conversation.thread.thread_type === "internal_ph_guidance" ? "No messages in this internal case thread yet." : "No messages in this support conversation yet.")}</li>`);
        }
        $$renderer3.push(`<!--]--></ol></div> `);
        if (newMessageCount > 0 || latestWindowEvicted) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex justify-center">`);
          Button($$renderer3, {
            variant: "outline",
            class: "min-h-11 w-full sm:w-auto",
            disabled: snapshotBusy,
            onclick: () => void jumpToLatest(),
            children: ($$renderer4) => {
              if (latestWindowEvicted && newMessageCount === 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`Return to latest messages`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`${escape_html(newMessageCount)} new ${escape_html(newMessageCount === 1 ? "message" : "messages")} · Jump to latest`);
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (canSend()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<form class="border-border/70 space-y-3 border-t pt-4"><div class="space-y-1"><label for="case-conversation-reply" class="text-sm font-medium">Your message</label> `);
          Textarea($$renderer3, {
            id: "case-conversation-reply",
            rows: 4,
            maxlength: 4e3,
            disabled: pending !== null || sendBusy,
            "aria-describedby": "case-conversation-composer-help",
            placeholder: "Write a message…",
            class: "min-h-24 resize-y",
            onkeydown: handleComposerKeydown,
            get value() {
              return draft;
            },
            set value($$value) {
              draft = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----> <p id="case-conversation-composer-help" class="text-muted-foreground text-xs">Press Enter for a new line. Press Ctrl+Enter or
                        Command+Enter to send.</p></div> `);
          if (pending) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm" aria-live="polite">`);
            if (sendState === "sending") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`This captured message is being sent. Its text and
                            retry identity are locked until the server confirms
                            it.`);
            } else if (sendState === "validation_error") {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`The server rejected this captured message. Its text
                            and identity are retained; abandon it explicitly
                            before editing.`);
            } else if (sendState === "conflict") {
              $$renderer3.push("<!--[2-->");
              $$renderer3.push(`This send identity is conflicted. The message may
                            already exist; retry only this exact text or
                            explicitly abandon it.`);
            } else if (sendState === "uncertain") {
              $$renderer3.push("<!--[3-->");
              $$renderer3.push(`The result is uncertain. The message may already
                            exist; retry uses the same text and identity.`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`This message is still pending and cannot be changed.`);
            }
            $$renderer3.push(`<!--]--></div> <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">`);
            Button($$renderer3, {
              type: "button",
              class: "min-h-11 w-full sm:w-auto",
              disabled: sendBusy || retryAvailableAt > Date.now() || !canSend(),
              onclick: retryPending,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(sendState === "sending" ? "Sending…" : sendState === "validation_error" ? "Retry exact message" : "Retry same message")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            if (!sendBusy) {
              $$renderer3.push("<!--[0-->");
              Button($$renderer3, {
                type: "button",
                variant: "outline",
                class: "min-h-11 w-full sm:w-auto",
                disabled: false,
                onclick: requestAbandon,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(abandonRequested ? "Confirm abandon" : "Abandon pending message")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> `);
            if (abandonRequested) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-destructive text-sm">The server may already have saved this message.
                            Confirming only stops retries; it cannot undo a
                            committed message.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">`);
            Button($$renderer3, {
              type: "submit",
              class: "min-h-11 w-full sm:w-auto",
              disabled: !draft.trim() || sendBusy || !canSend(),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(sendBusy ? "Sending…" : "Send message")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> <span class="text-muted-foreground text-xs">Only this conversation’s eligible participants can
                            read these messages.</span></div>`);
          }
          $$renderer3.push(`<!--]--></form>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-3 text-sm">${escape_html(conversation.thread.status === "closed" ? "This conversation is closed. Its history remains readable, but new messages are unavailable." : conversation.thread.status === "read_only" ? "This conversation is read-only. New messages are unavailable." : "Replies are unavailable for this conversation.")}</div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (feedback) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground text-sm"${attr("role", stale || sendState === "validation_error" || sendState === "conflict" ? "status" : "status")} aria-live="polite">${escape_html(feedback)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
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
function OfficeInquiryWorkspace($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      inquiries = [],
      next_cursor = null,
      destinations = [],
      initial = null,
      staff_mode = false,
      office_label = "",
      synthetic_only = true,
      principal_key,
      attempt_key,
      urls,
      unavailable = null
    } = $$props;
    let newOpen = false;
    let resolveOpen = false;
    let office = "";
    let subject = "";
    let body = "";
    let resolveCapture = null;
    let busy = false;
    let navigationLoading = false;
    let feedback = "";
    let failure = "idle";
    let feedbackOperation = "create";
    let fieldErrors = {};
    let retryBlocked = false;
    let retryAt = "";
    let controller = null;
    let retryTimer = null;
    let generation = 0;
    run(() => `${principal_key}\0${attempt_key}`);
    run(() => initial?.thread.id ?? "");
    const cannotRetry = derived(() => busy || retryBlocked || failure === "session" || failure === "denied" || failure === "conflict");
    const bodyLength = derived(() => Array.from(body).length);
    const subjectLength = derived(() => Array.from(subject).length);
    onDestroy(() => {
      generation += 1;
      controller?.abort();
      clearRetry();
    });
    function clearRetry() {
      if (retryTimer !== null) clearTimeout(retryTimer);
      retryTimer = null;
      retryBlocked = false;
      retryAt = "";
    }
    function resetFeedback(operation) {
      clearRetry();
      feedbackOperation = operation;
      feedback = "";
      fieldErrors = {};
      failure = "idle";
    }
    function beginInquiry() {
      if (busy) return;
      resetFeedback("create");
      newOpen = true;
    }
    function beginResolve() {
      if (busy || !initial || !urls.resolve) return;
      if (!resolveCapture) {
        resetFeedback("resolve");
        resolveCapture = {
          inquiry_id: initial.thread.id,
          idempotency_key: newIdempotencyKey(),
          url: urls.resolve
        };
      }
      resolveOpen = true;
    }
    function refreshTruth() {
      if (busy || navigationLoading) return;
      navigationLoading = true;
      router.reload({
        preserveScroll: true,
        onFinish: () => {
          navigationLoading = false;
        }
      });
    }
    function olderInquiries() {
      if (!next_cursor || navigationLoading || busy) return;
      navigationLoading = true;
      router.visit(urls.index, {
        data: { before_id: next_cursor },
        onFinish: () => {
          navigationLoading = false;
        }
      });
    }
    function rateLimit(response) {
      const header = response.headers.get("Retry-After");
      const seconds = header === null ? NaN : Number(header);
      const delay = Number.isFinite(seconds) && seconds >= 0 ? seconds * 1e3 : header ? Date.parse(header) - Date.now() : 1e4;
      const wait = Math.min(2147e6, Math.max(1e3, Number.isFinite(delay) ? delay : 1e4));
      clearRetry();
      retryBlocked = true;
      retryAt = new Date(Date.now() + wait).toLocaleTimeString();
      retryTimer = setTimeout(
        () => {
          retryBlocked = false;
          retryTimer = null;
        },
        wait
      );
    }
    function handleFailure(response, payload) {
      if (response.status === 401 || response.status === 419) {
        failure = "session";
        feedback = "Your session has expired. Sign in again before retrying. Nothing will be sent automatically.";
      } else if (response.status === 403 || response.status === 404) {
        failure = "denied";
        feedback = "This inquiry or office is no longer available to this account. Refresh to check current access.";
      } else if (response.status === 409) {
        failure = "conflict";
        feedback = "The inquiry, office availability, or operation identity has changed. Refresh to check the saved state before trying another action.";
      } else if (response.status === 422) {
        failure = "validation";
        feedback = "The server could not accept this request. Review the fields below before editing and reviewing a new draft.";
        if (payload.errors && typeof payload.errors === "object") {
          for (const [key, messages] of Object.entries(payload.errors)) {
            if (!["office", "subject", "body", "idempotency_key"].includes(key)) continue;
            const value = Array.isArray(messages) ? messages[0] : messages;
            if (typeof value === "string") fieldErrors[key] = value;
          }
        }
      } else if (response.status === 429) {
        failure = "limited";
        rateLimit(response);
        feedback = "Too many requests. Wait before retrying the same captured request.";
      } else {
        failure = "uncertain";
        feedback = "The server could not confirm the outcome. The action may already be saved. Retry only the same captured request, or refresh to check the saved state.";
      }
    }
    async function submit(operation) {
      const pending = resolveCapture;
      const url = resolveCapture?.url;
      if (!pending || !url || cannotRetry()) return;
      const requestGeneration = generation;
      const input = { idempotency_key: resolveCapture.idempotency_key };
      feedbackOperation = operation;
      feedback = "";
      fieldErrors = {};
      busy = true;
      controller = new AbortController();
      const requestController = controller;
      const timeout = setTimeout(() => requestController.abort(), 1e4);
      try {
        const response = await fetch(url, {
          method: "POST",
          credentials: "same-origin",
          cache: "no-store",
          redirect: "error",
          signal: requestController.signal,
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders()
          },
          body: JSON.stringify(input)
        });
        const result = await response.json().catch(() => null);
        if (requestGeneration !== generation) return;
        const payload = result && typeof result === "object" ? result : {};
        if (!response.ok) {
          handleFailure(response, payload);
          return;
        }
        if (operation === "create") ;
        else {
          if (String(payload.inquiry_id) !== resolveCapture?.inquiry_id || payload.status !== "closed") throw new Error("Unconfirmed receipt");
          resolveOpen = false;
          resolveCapture = null;
          failure = "idle";
          feedback = "The server confirmed this inquiry is closed. Refreshing its saved state…";
          navigationLoading = true;
          router.reload({
            preserveScroll: true,
            onFinish: () => {
              navigationLoading = false;
            }
          });
        }
      } catch {
        if (requestGeneration !== generation) return;
        failure = "uncertain";
        feedback = "No confirmed response was received. The action may already be saved. Retry the exact captured request to avoid duplicates; editing cannot undo a saved inquiry.";
      } finally {
        clearTimeout(timeout);
        if (requestGeneration === generation) {
          busy = false;
          controller = null;
        }
      }
    }
    function dateTime(value) {
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? "Time unavailable" : date.toLocaleString(void 0, { dateStyle: "medium", timeStyle: "short" });
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (unavailable) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="rounded-xl border border-border bg-card p-6" aria-labelledby="office-inquiries-unavailable"><h1 id="office-inquiries-unavailable" class="text-2xl font-semibold tracking-tight">Office inquiries</h1> <p class="mt-2 text-sm text-muted-foreground" role="status">${escape_html(unavailable.message)}</p></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<div class="min-w-0 space-y-5">`);
        if (synthetic_only) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-foreground" role="note"><p class="font-semibold">Synthetic demo only</p> <p>Use invented applicant information only. Do not send passwords, access keys, real personal information, or clinical and counselling details.</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <header class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0 space-y-1"><h1 class="text-2xl font-semibold tracking-tight">${escape_html(staff_mode ? `${office_label} inquiries` : "Office inquiries")}</h1> <p class="text-sm text-muted-foreground">${escape_html(staff_mode ? "Applicant inquiries assigned to your office. Open one conversation to reply or resolve it." : "Ask the Registrar or Guidance Office about your application. Select an inquiry to read its replies.")}</p></div> <div class="flex flex-wrap gap-2">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11",
          disabled: busy || navigationLoading,
          onclick: refreshTruth,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(navigationLoading ? "Loading…" : "Refresh page")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        if (!staff_mode && urls.create) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            type: "button",
            class: "min-h-11",
            disabled: busy || destinations.length === 0,
            onclick: beginInquiry,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->New inquiry`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></header> `);
        if (!staff_mode && destinations.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground" role="status">No office is available for a new inquiry right now. Existing conversations remain listed below. Refresh to check availability.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (navigationLoading) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="status" class="text-sm text-muted-foreground">Loading the current saved state…</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (initial) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap items-center justify-between gap-3">`);
          Link($$renderer3, {
            href: urls.index,
            class: "inline-flex min-h-11 items-center rounded-md text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Back to inquiry list`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> <div class="flex flex-wrap items-center gap-2"><span class="text-sm text-muted-foreground">${escape_html(initial.thread.office_label ?? office_label)}</span> `);
          if (staff_mode && urls.resolve && initial.thread.status === "open") {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "min-h-11",
              disabled: busy || navigationLoading,
              onclick: beginResolve,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Resolve inquiry`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></div> `);
          if (urls.snapshot && urls.send && urls.read) {
            $$renderer3.push("<!--[0-->");
            CaseConversation($$renderer3, {
              initial,
              snapshotUrl: urls.snapshot,
              sendUrl: urls.send,
              readUrl: urls.read,
              principalContext: principal_key,
              attemptContext: attempt_key
            });
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<p role="alert" class="rounded-lg border border-border p-4 text-sm">Conversation URLs are unavailable. Refresh the page to check access.</p>`);
          }
          $$renderer3.push(`<!--]--> `);
          if (feedbackOperation === "resolve" && feedback && !resolveOpen) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p role="status" class="text-sm text-muted-foreground">${escape_html(feedback)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<section${attr("aria-label", staff_mode ? "Office inquiry queue" : "Your inquiries")}${attr("aria-busy", navigationLoading)} class="min-w-0 overflow-hidden rounded-xl border border-border bg-card">`);
          if (inquiries.length === 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="space-y-2 p-6 text-center"><h2 class="text-base font-semibold">${escape_html(staff_mode ? "No inquiries in this office queue" : "No inquiries on this page")}</h2> <p class="text-sm text-muted-foreground">${escape_html(staff_mode ? "Applicant inquiries for your office will appear here." : "Start a new inquiry when you have an application question for an available office.")}</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<ul class="divide-y divide-border"><!--[-->`);
            const each_array = ensure_array_like(inquiries);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let inquiry = each_array[$$index];
              $$renderer3.push(`<li>`);
              Link($$renderer3, {
                href: inquiry.show_url,
                class: "flex min-w-0 flex-col gap-2 p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring focus-visible:-outline-offset-2 sm:flex-row sm:items-center sm:justify-between",
                children: ($$renderer4) => {
                  $$renderer4.push(`<div class="min-w-0 space-y-1"><p class="font-medium [overflow-wrap:anywhere]">${escape_html(inquiry.subject)}</p> <p class="text-sm text-muted-foreground">${escape_html(inquiry.office_label)} · <time${attr("datetime", inquiry.updated_at)}>${escape_html(dateTime(inquiry.updated_at))}</time></p></div> <div class="flex shrink-0 flex-wrap items-center gap-2">`);
                  if (inquiry.unread_count > 0) {
                    $$renderer4.push("<!--[0-->");
                    Badge($$renderer4, {
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->${escape_html(inquiry.unread_count)} unread`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--> `);
                  Badge($$renderer4, {
                    variant: inquiry.status === "open" ? "outline" : "secondary",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(inquiry.status === "open" ? "Open" : "Closed")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push(`<!----> <span class="text-sm text-primary">View inquiry</span></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          }
          $$renderer3.push(`<!--]--></section> `);
          if (next_cursor) {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "min-h-11",
              disabled: navigationLoading || busy,
              onclick: olderInquiries,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Older inquiries`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
      $$renderer3.push(`<!--]--> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return newOpen;
          },
          set open($$value) {
            newOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "max-h-[90dvh] overflow-y-auto sm:max-w-xl",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html("New office inquiry")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html("Choose an office and write your own message. You will review it before sending.")}`);
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
                  {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<form class="space-y-4"><div class="space-y-1"><label for="inquiry-office" class="text-sm font-medium">Office</label> `);
                    $$renderer5.select(
                      {
                        id: "inquiry-office",
                        value: office,
                        required: true,
                        class: "flex min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring",
                        "aria-invalid": !!fieldErrors.office,
                        "aria-describedby": fieldErrors.office ? "inquiry-office-error" : void 0
                      },
                      ($$renderer6) => {
                        $$renderer6.option({ value: "", disabled: true }, ($$renderer7) => {
                          $$renderer7.push(`Choose an office`);
                        });
                        $$renderer6.push(`<!--[-->`);
                        const each_array_2 = ensure_array_like(destinations);
                        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                          let destination = each_array_2[$$index_2];
                          $$renderer6.option({ value: destination.code }, ($$renderer7) => {
                            $$renderer7.push(`${escape_html(destination.label)}`);
                          });
                        }
                        $$renderer6.push(`<!--]-->`);
                      }
                    );
                    $$renderer5.push(` `);
                    if (fieldErrors.office) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p id="inquiry-office-error" class="text-sm text-destructive">${escape_html(fieldErrors.office)}</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div> <div class="space-y-1"><label for="inquiry-subject" class="text-sm font-medium">Subject</label> `);
                    Input($$renderer5, {
                      id: "inquiry-subject",
                      required: true,
                      maxlength: 120,
                      "aria-invalid": !!fieldErrors.subject,
                      "aria-describedby": "inquiry-subject-help",
                      get value() {
                        return subject;
                      },
                      set value($$value) {
                        subject = $$value;
                        $$settled = false;
                      }
                    });
                    $$renderer5.push(`<!----> <p id="inquiry-subject-help" class="text-xs text-muted-foreground">${escape_html(subjectLength())}/120 characters</p> `);
                    if (fieldErrors.subject) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p role="alert" class="text-sm text-destructive">${escape_html(fieldErrors.subject)}</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div> <div class="space-y-1"><label for="inquiry-body" class="text-sm font-medium">Message</label> `);
                    Textarea($$renderer5, {
                      id: "inquiry-body",
                      required: true,
                      rows: 6,
                      maxlength: 4e3,
                      class: "min-h-32 resize-y",
                      "aria-invalid": !!fieldErrors.body,
                      "aria-describedby": "inquiry-body-help",
                      get value() {
                        return body;
                      },
                      set value($$value) {
                        body = $$value;
                        $$settled = false;
                      }
                    });
                    $$renderer5.push(`<!----> <p id="inquiry-body-help" class="text-xs text-muted-foreground">${escape_html(bodyLength())}/4,000 characters. Enter adds a new line. Use synthetic details only; never include credentials or clinical information.</p> `);
                    if (fieldErrors.body) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<p role="alert" class="text-sm text-destructive">${escape_html(fieldErrors.body)}</p>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div> `);
                    if (Dialog_footer) {
                      $$renderer5.push("<!--[-->");
                      Dialog_footer($$renderer5, {
                        children: ($$renderer6) => {
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            class: "min-h-11",
                            onclick: () => {
                              newOpen = false;
                            },
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Cancel`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Button($$renderer6, {
                            type: "submit",
                            class: "min-h-11",
                            disabled: destinations.length === 0,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Review inquiry`);
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
                    $$renderer5.push(`</form>`);
                  }
                  $$renderer5.push(`<!--]-->`);
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
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return resolveOpen;
          },
          set open($$value) {
            resolveOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "max-h-[90dvh] overflow-y-auto sm:max-w-lg",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Resolve this inquiry?`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Closing the inquiry stops new replies. Its history remains readable. This action does not change the applicant's admission status or grant a policy exception.`);
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
                  $$renderer5.push(` <p class="font-medium [overflow-wrap:anywhere]">${escape_html(initial?.thread.subject)}</p> <p class="text-sm text-muted-foreground">${escape_html(initial?.thread.office_label ?? office_label)}. There is no reopen action.</p> `);
                  if (feedbackOperation === "resolve" && feedback) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p role="alert" class="text-sm">${escape_html(feedback)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (retryBlocked) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p role="status" class="text-sm text-muted-foreground">Retry available after ${escape_html(retryAt)}. The same confirmation identity will be reused.</p>`);
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
                          onclick: () => {
                            resolveOpen = false;
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Back to inquiry`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        if (failure === "conflict" || failure === "denied" || failure === "session") {
                          $$renderer6.push("<!--[0-->");
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            class: "min-h-11",
                            disabled: busy || navigationLoading,
                            onclick: refreshTruth,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Refresh saved state`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        Button($$renderer6, {
                          type: "button",
                          class: "min-h-11",
                          disabled: cannotRetry() || navigationLoading || !resolveCapture,
                          onclick: () => void submit("resolve"),
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(busy ? "Resolving…" : failure === "idle" ? "Confirm resolve" : "Retry exact resolve")}`);
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
  OfficeInquiryWorkspace as O
};
