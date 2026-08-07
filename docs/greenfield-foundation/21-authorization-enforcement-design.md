# Authorization Enforcement Design

**Artifact:** NEXIAM-GF-21
**Status:** DRAFT — REVIEW REQUIRED
**Authority:** Downstream of GF-04, GF-08, GF-13, GF-17, GF-18, GF-19, and GF-20; not implementation authority until the R5 readiness gate passes.
**Purpose:** Turn the role/capability/data-scope rules into one enforceable design for HTTP routes, Inertia page props, queries, commands, exports, background jobs, protected views, and assistant task commands.

## 1. Contract posture

NEXIAM uses capability- and resource-scoped authorization. A role label is input to capability assignment; it is not, by itself, an authorization decision. The browser may hide an action for usability, but only the application policy and owning command may permit it.

The following rules are non-negotiable:

1. **Default deny:** no capability/scope row means deny.
2. **Backend authority:** every protected read, mutation, export, job, document retrieval, and assistant task is checked server-side.
3. **Same command path:** a normal page action, an Inertia form, a browser retry, a queued job, and an authorized RAG task use the same typed application command or query contract.
4. **Resource scope:** office, program, cycle, session, admission-attempt, applicant-own, and purpose constraints are evaluated against the target resource, not just the actor record.
5. **Duty freshness:** Proctor authority is an active session-scoped duty; Test Administrator is an explicit, auditable Guidance capability. Revocation takes effect for new requests and active sessions are revalidated for sensitive actions.
6. **Protected-view evidence:** authorized reads of protected applicant/result/converted-score/consultation/program-projection data create attributable view evidence before the response is released.
7. **Break-glass is not routine authority:** Super Admin may operate the full audit-scope user view, but any break-glass use is reasoned, time-bounded, alerted, audited, and post-reviewed.
8. **No model authority:** a model response, retrieved document, browser prop, hidden route, or UI prompt cannot grant access or mutate domain state.
9. **Safe denial:** denial responses reveal neither whether an unrelated record exists nor which other actor could access it.
10. **Decision versioning:** mutation authorization and expected-version checks happen before the command changes state; stale data is a conflict, not an implicit overwrite.

## 2. Canonical authorization context

The application builds an `AuthorizationContext` after authentication and before resolving protected page data. The exact class/interface name is an implementation choice; the fields and semantics are contractual.

| Context field | Required meaning |
|---|---|
| `actor_id` / `actor_type` | Stable authenticated actor identity and actor category (`applicant`, staff actor, service actor, or break-glass session) |
| `base_roles` | Current non-deactivated role records; role strings are never sufficient for a decision |
| `capabilities` | Effective capabilities after role ceilings, office delegation, explicit grants, feature gates, and expiry |
| `office_scope` | Registrar/Guidance/other approved office scope; empty means no office authority |
| `program_scope` | Program IDs or approved program groups available to a Program Head or other scoped actor |
| `cycle_scope` | Academic-cycle scope; historical cycles are not silently merged with the active cycle |
| `duty_scope` | Active session-scoped duties, assignment IDs, start/end times, and revocation state |
| `purpose` | Declared reason/category for protected reads, exports, break-glass, or sensitive commands where required |
| `auth_time` / `session_version` | Recent-authentication and revocation/session invalidation signals |
| `break_glass` | Reason, start/end, reviewer/alert target, and allowed exceptional capabilities; absent for routine users |
| `request_id` / `correlation_id` | Traceability for the decision, command, view evidence, job, and user feedback |

A resource evaluation receives a `ResourceScope` containing the target type/ID, cycle, office, program, session, admission attempt, applicant identity, sensitivity class, lifecycle state, and expected version where applicable.

The policy result is a typed `AuthorizationDecision`:

- `allowed` with the effective capability, resolved scope, and required evidence/confirmation;
- `denied` with a safe stable reason category (`unauthenticated`, `capability_missing`, `scope_mismatch`, `duty_expired`, `lifecycle_forbidden`, `feature_gated`, `purpose_required`, `break_glass_required`);
- `requires_recent_authentication`;
- `requires_break_glass`; or
- `requires_confirmation` for a permitted mutation whose final execution requires an explicit user confirmation.

The user-facing message may be friendlier than the internal reason, but it must not disclose hidden record existence, other users, confidential score/conversion data, or policy implementation details.

## 3. Actor, overlay, and capability rules

The source of truth for the detailed matrix is `04-role-capability-and-data-scope-matrix.md`. The following summary is the enforcement interpretation.

| Actor/overlay | Allowed authority | Explicit denial |
|---|---|---|
| Applicant | Public submission; optional post-submission access setup; own status, schedule, released result, permitted policy/own-data assistant context; own confirmed inquiry | Any other applicant, pre-release score/result, staff data, raw answer sheet, conversion table, placement/admission decision |
| Registrar Administrator | Registrar intake oversight, application-window/reference setup, delegated Registrar accounts, acceptance/dismissal/correction within Registrar scope | Converted result/remark sheets and converted per-domain scores unless a separately approved capability exists; Guidance exam/scoring/release authority |
| Registrar Clerk | Daily Registrar intake/correction, acceptance/dismissal, approved institutional/reference setup pages | Support-staff management outside approved delegation, Guidance exam/scoring/release authority, raw/converted assessment evidence |
| Registrar Support Staff | Explicit delegated intake/correction, accept/dismiss, application-status visibility | Automatic Clerk/Admin inheritance, institutional/reference/setup pages, role management, converted scores/results |
| Guidance Counselor | Guidance counseling and approved assessment/release capabilities; inherent Test Administrator capability where approved; human decision authority | Registrar/HR setup authority, unrestricted cross-office data, autonomous model decisions |
| Guidance Clerk | Explicit Guidance operations; Test Administrator only when granted; approved task scope | Unassigned Proctor duty, unsupported release/decision capability, Registrar/HR authority |
| Guidance Support Staff | Explicit Guidance operational subset; Test Administrator/Proctor only via active grant/assignment | Automatic Guidance Clerk/Test Administrator inheritance, cross-office data |
| Proctor duty | Attendance/submission/session operations for the assigned ExamSession while duty is active | Global Proctor access, scheduling, scoring, release, unrelated session/applicant data |
| Program Head | Read-only program-scoped aggregates and approved consultation-facing/result evidence | Admission/score/release writes, raw answer sheets/scans, confidential conversion table, cross-program data |
| Staffing/HR | Permanent personnel and approved support-staff account/role lifecycle | Applicant/admission/exam/scoring/consultation/release decisions; Test Administrator/Proctor grants |
| Super Admin break-glass | Full audit-scope user view and exceptional technical/support recovery explicitly listed in the break-glass session | Routine business browsing or silent override of admission, score, counseling, or release outcomes |

### 3.1 Role ceilings and overlays

- A support-staff grant cannot exceed its office's approved ceiling.
- HR can create/deactivate approved personnel records but cannot grant Test Administrator or Proctor authority.
- Guidance Counselor may hold Test Administrator by default only where the approved role/capability record says so; Guidance Clerk and Guidance Support Staff require explicit grant/revoke records.
- A Proctor duty must identify the exam session, actor, grantor, start/end window, revocation state, and audit references.
- Feature flags can remove a capability from availability; they cannot expand a user's base authority.
- Break-glass capability lists are explicit. `*` is prohibited.
- Any GF-04 matrix value of `REVIEW` is fail-closed until its decision/evidence gate is closed; it is never interpreted as a soft allow, inherited role, or implementation default.
- Break-glass activation/end, audit-chain verification, and retention-hold placement/release use the typed GF-20 commands and are themselves subject to the same capability, purpose, confirmation, idempotency, and audit boundary.

## 4. Enforcement pipeline

Every protected route/query/command follows this order:

1. **Authenticate:** establish actor/session; rotate/revoke according to authentication contract.
2. **Resolve capability:** load non-deactivated role, grant, duty, feature-gate, and session-version records.
3. **Resolve target:** load only the minimal resource identity needed for policy evaluation; do not load a broad collection and filter in memory.
4. **Check lifecycle:** reject archived, released, finalized, closed, or otherwise immutable states when the requested action is not allowed.
5. **Check purpose:** require a safe purpose category for protected views, exports, audit queries, correction, override, or break-glass.
6. **Check scope:** evaluate applicant-own, office, program, cycle, session-duty, and resource ownership constraints.
7. **Check freshness/version:** for mutations, compare expected aggregate/version and reject stale writes with a conflict result.
8. **Require step-up/confirmation:** require recent authentication or explicit confirmation for sensitive operations.
9. **Record evidence:** append the decision/view/command audit event with actor, target, scope, purpose, outcome, command/correlation/idempotency IDs, and redacted before/after values as applicable.
10. **Execute or return:** only after the required evidence boundary succeeds may the query return protected data or the owning command mutate state.

For a protected read, the system must not return the sensitive payload if the mandatory view-evidence append fails. For a mutation, the audit event and state change share the owning transaction. External notifications, documents, inference, and provider calls remain after-commit effects.

## 5. HTTP, Inertia, jobs, exports, and assistant enforcement

### 5.1 Routes and page loads

- Authentication middleware may reject unauthenticated requests, but resource policy remains required.
- A route name or URL prefix is not a capability.
- Inertia page props are assembled only after the query policy succeeds. Hidden props are never used as a substitute for query scoping.
- A direct URL to an unauthorized resource returns a safe 403 page or a safe not-found response according to the resource-disclosure policy; it must not reveal an applicant name, result state, or existence through timing/message differences where avoidable.
- Parent navigation and breadcrumbs are generated from server-authorized route metadata. The client must not construct links to resources outside the resolved scope.
- A page with multiple panels evaluates each panel's capability independently. One denied/degraded panel must not cause an unrelated authorized panel to leak data or disappear without a state explanation.

### 5.2 Commands and forms

Every mutation request carries:

- actor/session context from the server, never trusted from the browser;
- typed input and validation errors;
- command ID and idempotency key;
- expected aggregate/version where the UI rendered one;
- declared purpose/reason where required;
- confirmation token/step for privileged, destructive, publish, finalize, force-open, close, unrelease, correction, or assistant-generated actions.

The server rechecks capability, scope, lifecycle, version, and feature gates at execution time. A disabled button is not evidence that the command is safe.

### 5.3 Jobs and retries

- A queued job re-resolves actor/command authorization when the job contract requires actor context; it never assumes a duty remains valid merely because it was valid when queued.
- Jobs carry command ID, idempotency key, target scope, source event, retry count, and terminal-failure state.
- Revoked duty, deactivated account, expired feature gate, or changed target lifecycle produces an explicit terminal/reconciliation result, not a silent retry loop.
- A job must not broaden scope during retry or use Super Admin fallback automatically.

### 5.4 Exports and documents

- Export is a separate capability from view.
- Export queries apply the same scope/purpose filters as the visible page and are generated server-side from an explicit snapshot/query version.
- A protected export records actor, filter/scope, purpose, row/aggregate class, format, generation/download evidence, and failure state.
- Spreadsheet formula neutralization, template encoding, private storage, watermarking, and download authorization follow GF-08.
- A client cannot turn an authorized page payload into an unauthorized export by requesting a broader URL parameter.

### 5.5 RAG and assistant task commands

The assistant may expose only an allowlisted typed command catalog. The application, not the model, resolves:

1. actor and capability;
2. target and resource scope;
3. current state/window/expected version;
4. whether the request is read-only or mutating;
5. confirmation requirement;
6. idempotency key;
7. audit and response redaction.

A policy answer may cite and link to a governing page. An own-data answer may return only released/authorized data. A staff task proposal is not execution; a mutation requires an explicit confirmation step in the assistant and is then dispatched to the same command used by the ordinary UI. Refusal must state the safe fallback and may offer the bounded applicant-confirmed Guidance inquiry path defined by D-079/EV-018. There is no direct model/database backdoor, hidden route, invented command, or prompt-based authorization.

## 6. UI-facing authorization states

The canonical browser behavior is defined in GF-22. Authorization-specific rules are:

| Situation | Browser behavior | Data disclosure rule |
|---|---|---|
| Unauthenticated | Redirect to the appropriate authentication/setup flow with a safe return intent | Do not include protected target details in the return URL |
| Authenticated but capability missing | Render a clear forbidden state or safe not-found state; provide an authorized parent/home link only | Do not name the hidden record or suggest another actor's access |
| Scope mismatch | Same safe denial treatment; no filter correction that reveals the record | Do not echo unauthorized IDs/details |
| Capability valid but feature gated | Show “not available in this environment/scope” with the approved fallback if one exists | Do not expose worker/model/provider internals |
| Duty expired/revoked | Stop sensitive controls, show reconciliation guidance, preserve non-sensitive local draft if safe | Do not allow queued/browser retry to bypass revalidation |
| Stale version/conflict | Show both the user's unsaved intent and the authoritative refreshed state where permitted; require review before retry | Do not merge silently |
| Break-glass required | Show reason/time-bound/reviewer requirements before activation; show persistent break-glass banner while active | Sensitive data appears only after the break-glass audit boundary succeeds |
| Protected view allowed | Show scope/purpose context and a safe access-history/audit indicator where approved | View evidence is recorded before payload release |

Disabled controls should include an accessible explanation when the user is authorized to see why an action is unavailable. When revealing the reason would disclose protected state, use the generic safe-denial state instead.

## 7. Audit and observability requirements

Each authorization decision or protected action must be traceable by:

- actor ID/type and effective capability/duty;
- target type/ID or aggregate class;
- cycle/office/program/session scope;
- action, command/query name, route/page identity;
- purpose/reason and confirmation state;
- request/correlation/command/idempotency IDs;
- decision outcome and failure category;
- expected/current version for conflicts;
- redacted old/new values for mutations;
- timestamp, user-agent/device class where approved, and source channel (`page`, `export`, `job`, `assistant`, `offline-replay`);
- related notification/document/inference/reconciliation IDs.

Metrics/logs must distinguish allowed, denied, stale/conflict, expired-duty, failed-audit, and provider-degraded outcomes. Logs contain no credentials, raw challenge, secret, full answer sheet, or unnecessary personal payload.

## 8. Failure and recovery contract

- **Authorization service/query failure:** fail closed for protected data; expose a retryable system error without falling back to an unscoped query.
- **Audit append failure:** abort the critical mutation or protected read response; surface an operator-visible incident.
- **Session revocation:** invalidate new requests immediately and revalidate sensitive actions; local drafts remain local-only until reauthorization.
- **Scope/grant change:** subsequent page props reflect the new capability set; stale browser controls fail safely at the command boundary.
- **Conflict:** never last-write-wins for scores, attendance, submission, release, acceptance, or assignment; route to refresh/review/manual resolution.
- **Provider/worker outage:** core manual page/command remains available; assistant/OMR/BGE/scheduling panels show degraded state and do not fabricate success.
- **Break-glass expiry:** immediately stop protected actions and require a new reasoned activation; queued work does not inherit the expired session.

## 9. Verification matrix

Before GF-21 promotion, each capability family requires:

1. one allowed server/API scenario;
2. one denied actor scenario;
3. one cross-office/program/cycle/session scope denial;
4. one revoked/expired duty scenario;
5. one stale-version conflict scenario for mutations;
6. one protected-view audit assertion;
7. one export/document scope assertion;
8. one browser route/page-prop assertion;
9. one assistant proposal/confirmation/command-path assertion where applicable;
10. one retry/terminal-failure/reconciliation assertion;
11. no unauthorized data in response, error, toast, notification, URL, log, or export.

The connected slice must prove at minimum:

- Applicant own-data isolation;
- Registrar Support Staff versus Registrar Clerk setup-page denial;
- Test Administrator capability versus ordinary Guidance access;
- Proctor assignment and revocation;
- Program Head program-scope and read-only behavior;
- Super Admin full audit-scope view with break-glass controls;
- OMR uncertainty and correction scope;
- RAG release/scope/refusal behavior;
- offline replay/conflict scope when that capability is promoted.

## 10. Promotion conditions and unresolved evidence

GF-21 may move from DRAFT only after:

- GF-04 role/capability rows and D-014–D-023/D-036/D-038–D-040/D-077/D-080 are propagated without contradictory role strings;
- GF-08 audit/privacy requirements and GF-20 command/query contracts reference the same authorization boundary;
- EV-001/EV-002/EV-004/EV-017/EV-018 close or explicitly bound institutional scope questions;
- negative browser/API tests, protected-view audit tests, and conflict/revocation tests are assigned in the R5 package;
- route/page/UI state behavior is reconciled with GF-22.

No unresolved evidence is silently replaced with a stakeholder claim. The matrix may carry a constrained assumption, but the assumption, owner, review gate, and fallback must remain visible.
