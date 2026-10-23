import { m as csrfHeaders } from "../app.js";
function governanceRoute(name, parameters = {}) {
  const p = typeof parameters === "number" ? { thread: parameters } : parameters;
  const part = (key) => {
    const value = p[key];
    if (value === void 0 || value === null) throw new Error(`Missing route parameter: ${key}`);
    return encodeURIComponent(String(value));
  };
  const base = "/setup/admission-support";
  if (name === "setup.admission-support.tabfm") return `${base}/tabfm`;
  if (name === "setup.quotas" || name === "setup.quotas.store") return "/setup/quotas";
  if (name === "threads.messages.store") return `/threads/${part("thread")}/messages`;
  if (name === "program-head.threads.open") return `/staff/programs/${part("program")}/applicants/${part("attempt")}/threads`;
  if (name === "setup.admission-support.advising-note.use") return `${base}/applicants/${part("attempt")}/advising-note/use`;
  if (name.startsWith("setup.quotas.registrar-grants.")) {
    return `/setup/quotas/programs/${part("program")}/cycles/${part("cycle")}/registrar-grants${name.endsWith(".revoke") ? "/revoke" : ""}`;
  }
  const action = name.replace("setup.admission-support.", "");
  if (["benchmarks.import", "benchmarks.publish", "benchmarks.activate"].includes(action)) {
    return `${base}/cycles/${part("cycle")}/benchmarks/${action.split(".")[1]}`;
  }
  const paths = {
    "governance.show": "governance",
    "benchmarks.draft.save": "benchmarks/draft",
    "benchmarks.draft.start": "benchmarks/draft/start",
    "benchmarks.draft.recover": "benchmarks/draft/recover",
    "benchmarks.draft.restore": "benchmarks/draft/restore",
    "benchmarks.draft.rebase": "benchmarks/draft/rebase",
    "benchmarks.history": "benchmarks/history",
    "benchmarks.comparison": "benchmarks/comparison",
    "benchmarks.subject-bindings.approve": "benchmarks/subject-bindings/approve",
    "benchmarks.subject-bindings.apply": "benchmarks/subject-bindings/apply",
    "sandbox.governance-preview": "sandbox/preview",
    "advising-note.save": "advising-note/draft",
    "advising-note.restore": "advising-note/restore",
    "advising-note.publish": "advising-note/publish",
    "advising-note.retract": "advising-note/retract",
    "advising-note.copy": "advising-note/copy",
    "discussions.open": "discussions",
    "discussions.history": "discussions/history",
    "discussions.send": "discussions/messages",
    "discussions.read": "discussions/read",
    "discussions.state": "discussions/state"
  };
  if (!paths[action]) throw new Error(`Unknown governance route: ${name}`);
  return `${base}/programs/${part("program")}/cycles/${part("cycle")}/${paths[action]}`;
}
class GovernanceRequestError extends Error {
  constructor(message, status, errors = {}, outcomeUnknown = false) {
    super(message);
    this.status = status;
    this.errors = errors;
    this.outcomeUnknown = outcomeUnknown;
    this.name = "GovernanceRequestError";
  }
  status;
  errors;
  outcomeUnknown;
}
async function governanceRequest(url, payload, signal) {
  const response = await fetch(url, {
    method: payload === void 0 ? "GET" : "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...payload === void 0 ? {} : { "Content-Type": "application/json" },
      ...csrfHeaders()
    },
    ...payload === void 0 ? {} : { body: JSON.stringify(payload) },
    signal
  });
  const data = await response.json().catch(() => null);
  if (!response.ok) {
    throw new GovernanceRequestError(data?.message ?? (response.status === 419 ? "Your session expired. Sign in again before retrying." : "The request could not be completed. Retry without changing your input."), response.status, data?.errors ?? {});
  }
  if (data === null) throw new GovernanceRequestError("The server returned an unreadable response. Retry the same request.", response.status, {}, true);
  return data;
}
function governanceError(error) {
  if (error instanceof GovernanceRequestError) {
    const details = Object.values(error.errors).flat().join(" ");
    return `${error.status === 409 ? "Conflict: " : error.status === 403 ? "Access is no longer available: " : ""}${error.message}${details ? ` ${details}` : ""}`;
  }
  return error instanceof Error ? error.message : "The request failed. Your input has been kept.";
}
function isUncertainCommandOutcome(failure) {
  if (!(failure instanceof GovernanceRequestError)) return true;
  return failure.outcomeUnknown || failure.status === 408 || failure.status === 425 || failure.status === 429 || failure.status >= 500;
}
function reusePendingGovernancePayload(action, payload, pending) {
  return pending?.action === action ? pending.payload : payload;
}
function beginGovernanceRequest(scope, previousSequence) {
  return { scope, sequence: previousSequence + 1 };
}
function isCurrentGovernanceRequest(token, scope, sequence) {
  return token.scope === scope && token.sequence === sequence;
}
function applyGovernanceNoteDetail(state, data) {
  const detail = data.note ?? null;
  const latestDraft = detail?.draft ?? null;
  const hasDirtyText = state.text !== (state.draft?.content ?? "");
  return {
    ...state,
    selectedNote: detail,
    draft: hasDirtyText ? state.draft : latestDraft,
    latestDraft,
    published: detail?.published ?? null,
    history: detail?.history ?? { items: [], pagination: null },
    publicationHistory: detail?.publication_history ?? { items: [], pagination: null },
    text: hasDirtyText ? state.text : latestDraft?.content ?? "",
    selectedNoteId: latestDraft?.note_id ?? detail?.note_id ?? state.selectedNoteId
  };
}
function reconcileGovernanceNoteEditor(state) {
  const draft = state.latestDraft ?? null;
  return { draft, text: draft?.content ?? "" };
}
function focusGovernanceTarget(id) {
  const target = document.getElementById(id);
  if (!target) return;
  const details = target.closest("details");
  if (details) details.open = true;
  const focusTarget = () => {
    target.scrollIntoView?.({ block: "nearest" });
    if (target instanceof HTMLElement) target.focus({ preventScroll: true });
  };
  if (typeof requestAnimationFrame === "function") requestAnimationFrame(focusTarget);
  else focusTarget();
}
function invalidatePreview(state) {
  return { ...state, current: null, status: state.result === null ? "idle" : "stale", error: "" };
}
export {
  GovernanceRequestError as G,
  governanceRoute as a,
  governanceError as b,
  beginGovernanceRequest as c,
  invalidatePreview as d,
  isUncertainCommandOutcome as e,
  focusGovernanceTarget as f,
  governanceRequest as g,
  applyGovernanceNoteDetail as h,
  isCurrentGovernanceRequest as i,
  reconcileGovernanceNoteEditor as j,
  reusePendingGovernancePayload as r
};
