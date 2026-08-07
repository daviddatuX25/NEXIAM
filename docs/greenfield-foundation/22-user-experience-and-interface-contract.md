# User Experience, Route, and Interface State Contract

**Artifact:** NEXIAM-GF-22
**Status:** DRAFT — REVIEW REQUIRED
**Authority:** Downstream of GF-03, GF-04, GF-06, GF-08, GF-11, GF-13, GF-17, GF-18, GF-20, and GF-21; not implementation authority until R5 passes.
**Purpose:** Define the browser experience as an executable product contract: shells, navigation, breadcrumbs, route/page responsibility, forms, tables, charts/graphics, notifications/toasts, loading/empty/error/conflict/offline states, accessibility, responsive behavior, privacy, and evidence expectations.

## 1. Why this artifact exists

The NEXIAM greenfield plan is not complete when it specifies tables, commands, and APIs but leaves the browser to infer what a user sees or what a failed action means. The UI must expose the same business truth, authorization scope, state transitions, and recovery choices as the backend.

This contract promotes useful legacy evidence without treating legacy screens as authority. The inspected legacy frontend demonstrates staff/applicant shells, responsive sidebar navigation, top breadcrumb rendering, notification polling, toast/flash handling, Chart.js line/bar/doughnut charts, table/card view switching, URL-backed filters, schedule-assistant panels, AI chat, QR/camera states, and score-import preview. GF-22 defines the target behavior and corrects any legacy ambiguity.

The target presentation baseline is PHP 8.4/Laravel 13, Inertia 3, React 19, TypeScript 5.9, and Tailwind 4 under the revised D-055. Exact component names and route URIs remain implementation decisions; the responsibility, state, security, and user-feedback rules below do not.

## 2. Non-negotiable UX principles

1. **The server is the source of page truth.** Inertia props, query results, capabilities, lifecycle state, and flash/event data come from authorized server contracts.
2. **The UI is not authorization.** Hidden/disabled controls improve usability only; every direct URL, form, export, job, and assistant command is rechecked under GF-21.
3. **Every meaningful state has a visible state.** Loading, empty, denied, invalid, conflict, degraded, offline, queued, succeeded, and failed outcomes are designed rather than implied.
4. **User intent is preserved safely.** Validation, network failure, stale data, and provider failure must not erase a typed form, silently duplicate a mutation, or misreport an authoritative result.
5. **Feedback describes the committed truth.** “Saved” means the authoritative command committed. “Queued” means the side effect is pending. “Sent” means delivery evidence exists; otherwise show pending/failed.
6. **Charts explain; they do not decide.** A chart is a scoped read projection with a textual/table alternative and cannot become an admission, scoring, placement, or release authority.
7. **Accessibility is part of correctness.** Keyboard flow, focus, labels, live regions, contrast, reduced motion, text/table alternatives, and touch targets are acceptance requirements.
8. **Scope is visible but not verbose.** Users should understand the cycle/office/program/session scope of a page without seeing data they are not allowed to see.
9. **No silent omission.** A gated/deferred capability is represented as unavailable/degraded/coming through an approved fallback where the user would otherwise expect it.
10. **One interaction pattern, one command contract.** Page actions, responsive variants, keyboard shortcuts, and authorized assistant actions resolve to the same backend command.

## 3. Application shells and global page anatomy

### 3.1 Staff shell

The staff shell is used by Registrar, Guidance, Proctor, Program Head, and approved Super Admin contexts. It contains, in this order:

1. skip-to-content link;
2. persistent but responsive side navigation;
3. top bar with product identity, current user/office context, notification center, theme/preferences controls where approved, and session/break-glass indicator;
4. breadcrumb trail at the top of the content region;
5. page heading, scope/context line, and page-level actions;
6. optional status/banner region for degraded, stale, offline, or break-glass state;
7. main content panels/tables/forms/charts;
8. persistent toast live region outside the scrolling content;
9. contextual help/assistant only when the actor/capability/feature flag permits it.

The side navigation is server-authorized and grouped by domain, for example Admissions, Examination, Assessment, Consultation/Release, Reports, and System/Audit. A section with no authorized items is omitted. A direct URL remains protected even if a user manually types it.

### 3.2 Applicant shell

The applicant shell is simpler and own-data scoped. It contains product/portal identity, status/schedule/result navigation, notification center, account/recovery controls, breadcrumbs for multi-step portal journeys, a main content region, help/policy links, and a footer. The assistant appears only when enabled and must disclose whether it is answering policy, own-data, or inquiry-handoff context.

### 3.3 Proctor/session shell

The Proctor experience prioritizes one assigned session: current session identity, effective deadline, connection/degraded status, roster/attendance/submission actions, and recovery instructions. Navigation to unrelated sessions or grading/release pages is absent and denied server-side. The session status remains visible while scrolling and on narrow screens.

### 3.4 Super Admin break-glass shell

When break-glass is active, the shell displays a persistent banner containing the reason/category, activation time, expiry time, reviewer/alert state, and a link that invokes the typed `EndBreakGlassSession` command. Activation uses the typed `ActivateBreakGlassSession` command with strong authentication, a non-wildcard capability allowlist, reason, reviewer/alert target, duration, confirmation, and audit evidence. The banner cannot be dismissed as if it were a normal toast. Every audit-view/export/technical action remains visibly marked as break-glass and produces audit evidence.

### 3.5 Canonical page props

Every Inertia page contract should expose a typed equivalent of:

- page identity and version;
- title, description, and scope/context summary;
- server-generated breadcrumbs;
- authorized navigation metadata or capability summary needed for rendering;
- initial/deferred view data and data freshness timestamp;
- query/filter/sort/pagination state;
- action availability with stable reason category where safe to disclose;
- form defaults, validation errors, expected versions, and idempotency seed/reference;
- flash/event feedback with correlation ID;
- notification summary and unread state where allowed;
- `page_state`/panel state and retry metadata;
- degraded/offline/sync metadata where relevant;
- audit/protected-view indicator where the page is sensitive.

No page prop may contain an unauthorized sibling record merely because another panel on the page is authorized.

## 4. Navigation, routes, and breadcrumbs

### 4.1 Route responsibility

Exact URI strings are deferred to implementation, but route responsibility is fixed:

| Experience area | Page responsibility | Primary actors |
|---|---|---|
| Public intake | Explain process/policy, create admission attempt, show validation and submission outcome | Applicant/public |
| Applicant access | Setup/recovery, own dashboard, application status, schedule, released result, notifications, bounded inquiry | Applicant |
| Registrar intake | Queue, filters, application detail, correction, accept/dismiss, status/history, approved setup/reference pages | Registrar Admin/Clerk/authorized Support Staff |
| Guidance operations | Session plan, slot-reservation lifecycle, manual assignment, direct assessment, session monitoring, consultation availability | Test Administrator capability |
| Proctor session | Assigned roster, attendance, submission, close/reconciliation, degraded/offline status | Assigned Proctor/Test Administrator |
| Assessment/grading | Grading session, manual scores, OMR proposals/review, import preview, correction/version, finalization | Test Administrator/authorized Guidance |
| Consultation/release | Consultation decision, result preparation/print, internal preview, release/unrelease, notification status | Guidance authority/Test Administrator as granted |
| Reports | Scoped KPIs, pipeline, demographics/aggregates, exports, data freshness and metric definitions | Registrar/Guidance/Program Head/Super Admin as scoped |
| Audit/compliance | Search/filter/verify audit events, protected-view history, break-glass review | Super Admin and explicitly approved reviewers |
| RAG/assistant | Policy citations, own-data context, typed task proposals, confirmation, refusal/fallback | Actor-specific |
| Operations | Health/degraded/reconciliation, retention-hold/purge, backup/restore, and audit-verification evidence | Break-glass/approved operations |

Each page declares its owning capability, read scope, mutation commands, protected data class, and allowed parent/child navigation in the R5 route catalog.

### 4.2 Breadcrumb rules

Breadcrumbs are required at the top of every authenticated multi-page workflow and every staff page with a domain context. A single-root page may show the page title in the breadcrumb region; it must still expose the page heading and document title.

- The server supplies an ordered breadcrumb array with human-safe labels and authorized `href`s.
- The final crumb is the current page, is not a link, has `aria-current="page"`, and matches the page heading/document title.
- Earlier crumbs are links only when the actor is authorized for the parent route and the parent is safe to disclose.
- Labels use domain language, not raw database IDs. Resource labels may be masked/truncated according to privacy rules.
- Cycle, office, program, and session context appears as a scope crumb or context bar when it changes the meaning of data.
- Filters, sort, pagination, and tab state are preserved when navigating back to a list unless the user explicitly clears them. A breadcrumb to a parent list must not accidentally broaden scope.
- On narrow screens, the trail collapses to a compact menu showing the current page and authorized ancestors. The full path remains keyboard and screen-reader accessible.
- Breadcrumbs are not a replacement for back/cancel actions, and back/cancel behavior must not lose a dirty form without warning.
- Unauthorized or missing parents are omitted or replaced with the safe home/dashboard link; the UI never exposes a forbidden parent through breadcrumb text.
- Deep links opened in a new tab establish the correct shell, scope, breadcrumb, and page state without relying on prior client memory.

### 4.3 Navigation transitions

- Inertia navigation shows an accessible progress indicator and preserves scroll only where the action is a filter/pagination update or the page contract explicitly requests it.
- A navigation away from a dirty form prompts with a clear, non-destructive confirmation. Browser refresh/close uses the platform before-unload safeguard only while a meaningful unsaved draft exists.
- After a successful mutation, the user lands on the state that proves the command result, not merely on a generic dashboard.
- After a failed mutation, remain on the form/detail view with recoverable input and an actionable error.
- Back navigation after a conflict returns to the last authorized list/context, not to an unauthorized or stale detail URL.

## 5. Shared component contracts

### 5.1 Page header and scope bar

A page header contains a title, one-sentence purpose, scope/context (cycle/office/program/session), optional freshness, and actions ordered by risk. Destructive/privileged actions are visually and semantically distinct. The header never claims a chart/report is current without a server-provided freshness timestamp.

### 5.2 Tables, lists, and cards

- Search, filters, sort, pagination, and view mode are URL-backed when they define the page result; a reload/deep link reproduces the same result.
- Filter changes reset pagination when the result set changes. Search is debounced only for harmless reads; authoritative mutations are never debounced into ambiguous commands.
- Desktop tables may become cards or horizontally scrollable tables on narrow screens. Important identity/status/action fields remain visible; hiding a column never hides a required safety fact.
- Table headers use semantic sort controls and expose current sort direction. Row actions identify their target textually, not by icon alone.
- Empty states distinguish `no records exist`, `no records match filters`, `no records in this scope`, `not yet available`, and `not authorized`.
- Large lists show page position/total where safe, loading/refresh status, and a retry action on query failure.
- Bulk actions show selected count, scope, affected-state summary, validation failures, and a confirmation when the command is irreversible or publishes/finalizes data.
- Card/table view switches are preference-only; both views use the same authorized view model and action contract.

### 5.3 Forms and fields

- Every input has a visible label or an equivalent accessible label, a stable error target, help text where needed, and a server/client validation distinction.
- Client validation improves speed but cannot replace server validation. Server errors remain associated with fields and also appear in a top summary for long forms.
- Invalid submit moves focus to the summary or first invalid field; field errors are announced without duplicating the entire form.
- Submit controls show `saving`, `checking`, `processing`, `queued`, or `retry` according to the actual command state. A second click cannot create a second authoritative command.
- Inputs retain values after validation/network errors unless the server explicitly rejects them for safety. Sensitive values are not echoed in errors, URLs, logs, or toasts.
- Expected version and idempotency references are part of the form contract but are not editable user fields.
- A successful command clears only the relevant dirty state and renders the authoritative response; a queued side effect remains visibly pending.

### 5.4 Dialogs, drawers, and confirmations

Use a modal dialog for an action that is destructive, irreversible, privileged, publishes public/applicant-visible data, finalizes/reopens/corrects authoritative scores, force-opens/extends/closes sessions, creates a bounded inquiry, or executes an assistant mutation.

The confirmation must state:

- the action and target;
- relevant current status and scope;
- irreversible/visibility/notification consequences;
- required reason or effective deadline;
- whether the command is queued or immediate;
- the final confirm/cancel controls.

Dialogs trap focus, close on Escape only when the action is not mid-commit, return focus to the trigger, and never rely on color alone. A cancel/close action does not submit a partial form.

### 5.5 Notifications center

The notification center is a durable, actor-scoped projection of domain events. It supports unread count, read/unread state, safe target navigation, empty/loading/error states, and explicit retry for read-state failure. It must not display a protected message to an unauthorized actor.

Polling or push is bounded and deduplicated. A new notification does not generate a toast for every poll, duplicate event, or already-open notification. Mark-read is idempotent and has visible failure feedback.

## 6. Toast and feedback contract

### 6.1 Toast payload

A toast has a typed payload equivalent to:

- `id` and `dedupe_key`;
- severity: `success`, `info`, `warning`, or `error`;
- short title and actionable message;
- source action/page and correlation/command ID;
- optional safe link/action;
- `duration_class`: short, normal, persistent-until-dismissed;
- `requires_attention` and `announce` flags;
- redaction classification.

### 6.2 When to use which channel

| Outcome | Toast | In-page treatment |
|---|---|---|
| Authoritative command committed | Short success toast; link to resulting view when useful | Render updated state and audit/notification status |
| Side effect queued after commit | Info/success-with-pending wording | Show pending job/delivery state with retry/reconciliation path |
| Provider degraded but core state committed | Warning toast | Persistent degraded banner/panel and manual fallback |
| Command failed before commit | Error toast only as supplement | Inline/server error with retry and preserved input |
| Validation failed | Usually no toast or a concise summary toast | Field errors and focus summary are mandatory |
| Permission denied | No generic “failed” toast | Safe denied page/inline state; no protected details |
| Stale/conflict | Warning/error toast may point to conflict | Show authoritative refresh and user intent; require review before retry |
| Background notification arrived | Info toast only when deduplicated and not already visible | Notification center retains the item |

Toasts never claim delivery, release, score finalization, or publication unless the corresponding authoritative/side-effect evidence exists. “Email sent” is prohibited when only “delivery queued” is known.

### 6.3 Duration and accessibility defaults

- Short success/info: approximately 4–6 seconds, with a visible dismiss control.
- Warning: approximately 8–12 seconds when recoverable; persistent when action is required or the page is degraded.
- Error/action-required: persistent until dismissed or resolved.
- Maximum visible stack is bounded; older non-critical toasts collapse or are placed in a notification history.
- Toasts appear in a consistent viewport region, avoid covering primary controls, and become full-width/edge-safe on narrow screens.
- The live region uses `role="status"` for non-urgent information and `role="alert"` for errors/action-required messages. It is not the sole place where an error is rendered.
- Color, icon, sound, and motion are supplementary. Sound is user-controllable and never the only signal.
- Toast content is redacted and must not include credentials, raw scores, full applicant details, or hidden-resource existence.

## 7. Loading, empty, error, conflict, and degraded states

Every page or deferred panel declares one of these states:

| State | Required behavior |
|---|---|
| `initial_loading` | Skeleton matching the final layout; no misleading zero values; accessible loading announcement |
| `refreshing` | Preserve current safe data, show non-blocking refresh indicator, prevent duplicate destructive action |
| `ready` | Render authorized data, scope, freshness, available actions, and current version |
| `empty_scope` | Explain that no records exist in this authorized scope and provide the next safe action |
| `empty_filter` | Explain that filters match nothing and offer clear-filter action |
| `not_yet_available` | Explain prerequisite/phase or queued computation and show retry/status where appropriate |
| `denied` | Safe forbidden/not-found state with authorized navigation; no leakage |
| `duty_expired` | Stop protected controls, show reassignment/reauthorization path, preserve only safe local draft |
| `feature_gated` | Explain that the capability is not promoted in this environment/scope and show the approved fallback |
| `purpose_required` | Request the permitted purpose/confirmation without exposing the protected target |
| `break_glass_required` | Show time-bound activation, reason, reviewer, and persistent-banner requirements before release |
| `validation_error` | Field and summary errors, focus management, preserved safe input |
| `server_error` | Human-readable failure ID/correlation, retry, fallback, and operator-safe diagnostic path |
| `conflict` | Authoritative current value plus the user's pending intent; review/refresh/merge choice, never silent overwrite |
| `accepted_for_processing` | Show durable operation ID, pending progress/reconciliation, and no completion claim |
| `degraded_provider` | Core manual fallback remains available; provider-specific panel shows unavailable/pending and no fabricated output |
| `unknown_outcome` | Stop duplicate retry, show reconciliation status, and route to an authorized operator when required |
| `audit_failed` | Fail closed for the protected panel/action, show a safe incident/retry state, and emit no sensitive payload |
| `offline_ready` | Explicit device/session scope, last synchronization time, permitted offline operations, and connectivity indicator |
| `offline_pending` | Durable outbox count/oldest operation, ordered replay status, no claim of server commit |
| `offline_conflict` | Stop automatic replay for the conflicting operation, show safe before/after/context, require authorized human resolution |
| `manual_reconciliation_required` | Quarantine the operation and show the authorized human-resolution path; no silent retry or discard |
| `success_pending_effect` | Authoritative state is visible; notification/document/provider work remains visibly queued/retryable |
| `not_found` | Safe resource-not-found response; do not distinguish unauthorized existence where policy forbids it |

Deferred Inertia props use an empty/skeleton state and expose retry/error state; they do not silently render an empty chart or zero KPI as if data were loaded.

## 8. Charts, dashboards, and graphics contract

### 8.1 Approved purposes

Charts and graphics are read-only explanations of authorized projections. Candidate uses include:

- pipeline/funnel counts across admission, scheduling, attendance, grading, and release milestones;
- time-series volume or completion trends by selected cycle/scope;
- composition charts for approved demographic or status aggregates;
- KPI cards for counts with explicit denominator, scope, and freshness;
- program-scoped projection graphics for Program Head where privacy/aggregation rules pass;
- OMR review graphics showing a paper image, detected regions, confidence/uncertainty, and reviewer corrections;
- document/result previews, status timelines, and workflow diagrams that explain state without mutating it.

Charts must not be used to infer an individual admission decision, score, placement, release, or quota override. A chart click may navigate to an independently authorized filtered view; it may not mutate state.

### 8.2 Chart data contract

Every chart or graphic receives a typed view model equivalent to:

- stable chart ID and title;
- one-sentence purpose and accessible description;
- source projection/query and correlation/freshness timestamp;
- selected cycle/office/program/session scope;
- unit, denominator, aggregation rule, timezone, and date range;
- ordered categories/series with labels and values;
- null/suppressed/insufficient-privacy markers distinct from zero;
- approved color/pattern/icon tokens and semantic meaning;
- authorized drill-down route/filter, if any;
- table/CSV alternative where export is approved;
- data-state and error/retry metadata.

The UI must not calculate a new business metric from raw rows merely to draw a chart. Metric definitions live in the reporting/query contract and are referenced in the chart caption or details panel.

### 8.3 Chart and graphic selection

| Visual | Use | Required alternative/guard |
|---|---|---|
| KPI card | One bounded count or rate | Label, unit, denominator/scope, freshness, and link to detail where authorized |
| Horizontal bar/funnel | Ordered pipeline stages or category comparison | Text/table values; no misleading truncated baseline; counts and percentages defined |
| Line/area | Trend over time | Date granularity/timezone, missing-date treatment, accessible table, no decorative smoothing that changes meaning |
| Doughnut/composition | Small number of mutually exclusive categories | Legend with values/percentages, table alternative, do not use for many categories or tiny differences |
| Table/timeline | Exact values, audit/review, small-screen fallback | Semantic headings, sort/filter state, export scope and formula safety |
| OMR sheet/region graphic | Review evidence and human correction | Textual per-item observation list, zoom/pan controls, confidence/state legend, no automatic authority |
| Status/timeline graphic | Milestones and prerequisites | Current state text, dates, actor/command evidence, accessible list alternative |
| Empty/degraded illustration | Orientation only | Must not imply success, failure, or a real metric; include text and next action |

No 3D, perspective, decorative gauge, unexplained color scale, or animation is used for a consequential metric. Axis baselines, units, rounding, and suppression are explicit. Color is never the only carrier of green/uncertain/red or status meaning; use labels, icons/patterns, and text.

### 8.4 Chart interaction and state behavior

- Initial chart load uses a layout-matched skeleton, not a blank canvas.
- No-data is different from loading and from query failure. A no-data panel says whether there are no records, filters match nothing, or data is withheld by privacy threshold.
- A stale chart shows its last-updated time and a refresh/retry action; it does not silently present old data as current.
- Tooltips supplement visible labels and are keyboard/focus accessible where the library permits; important values are present in the text/table alternative.
- Legend items identify series, units, and scope. Toggling a series does not change authoritative data or export scope.
- Drill-down links carry the selected authorized scope/filter and show the resulting breadcrumb. The server rechecks authorization.
- Chart export is separate from view and emits protected export audit evidence. CSV/XLSX output follows formula-neutralization rules.
- Theme changes, responsive resizing, reduced-motion settings, and print mode must preserve meaning and contrast. Canvas/SVG graphics require a text alternative.
- If a chart query fails, retain safe surrounding panels and show the chart-specific retry/error state; never replace failure with zero.

### 8.5 OMR and document graphics

The OMR review workspace shows the approved sheet/template version, scan identity, candidate binding confidence, per-item green/uncertain/red observation, image/region zoom, detected mark details, reviewer before/after value, reason, and save/review status. Reviewers can explicitly confirm blank/no-bubble answers and correct Applicant No/binding. The scanner/worker cannot finalize scores or accept/reject a candidate.

Images remain private and scoped. Download/print is a separate audited capability. A result/document preview is watermarked as internal until release and never exposes a predictable public file path. Print mode includes the document version, scope, and appropriate privacy handling.

## 9. Workflow-specific browser behavior

### 9.1 Applicant intake and portal

- Public form shows required/optional fields, cycle/window, privacy/purpose notice, validation summary, and submission progress.
- Successful submission displays the Applicant No/Reference, safe next steps, access-setup delivery state, and a non-secret reminder that the reference is not a password. It does not imply acceptance.
- Delivery failure leaves the admission attempt committed and shows retry/staff-assisted recovery guidance without fabricating an email.
- Applicant dashboard distinguishes `submitted`, `pending`, `needs_correction`, `accepted`, `dismissed`, `scheduled`, `attended`, `result_pending`, and `released` projections from authoritative records.
- Pre-release score/result panels are absent or show a safe not-yet-released state; own-data access never includes another applicant.
- Notification/read failures are retryable and do not block viewing the authoritative portal state.

### 9.2 Registrar application review

- Queue shows only authorized Registrar scope, canonical decision vocabulary, filters, status history, correction indicators, and version/freshness.
- Support Staff sees delegated intake/decision/status actions but no setup/reference pages. The UI does not show a disabled setup link if its existence would disclose a restricted administration surface.
- Accept/dismiss/reopen/correction actions show current state, reason requirement, expected version, and confirmation level. A stale record returns a conflict state.
- Bulk actions show selected scope and row-level failures; a partial result is explicit and resumable, not a false all-success toast.

### 9.3 Scheduling and Direct Assessment

- Reservation lifecycle is visible: `open_for_slot_reservation`, `reservation_closed/finalized`, and `schedule_published`.
- Applicants can reserve only while the Test Administrator has opened the reservation window. The user sees capacity/date/time/room constraints and a clear reservation result.
- Test Administrator can bypass reservation for authorized late/small cohorts through an explicitly labeled manual-assignment path. Direct Assessment is separately labeled as the late/same-day bypass path.
- Publish is distinct from reserve/finalize. Notification status says not-yet-notified, queued, sent, failed, or not applicable. Publication is confirmed before applicant-visible effects.
- Force-open/extend/close requires reason, effective window, confirmation, and an audit-sensitive result. The Proctor sees the effective deadline, not an arbitrary original date.
- Schedule-assistant proposals show constraints, warnings, feasible/infeasible result, explanation, and manual alternative. Applying a proposal is a normal confirmed command; the assistant cannot silently publish or assign.

### 9.4 Proctor session and offline capability

- The assigned session header always shows session, applicant/roster scope, effective start/end/grace, duty status, connectivity, last sync, and pending operations.
- Online attendance and submission show immediate authoritative commit or explicit failure. Retry is idempotent.
- If offline proctoring is promoted after EV-011, only the approved session/device scope is available offline. The UI clearly labels local capture versus server-committed state.
- The offline outbox shows operation order, IDs, timestamps, replay status, and oldest pending item. Reconnect replays in order; duplicate acknowledgements are safe.
- A conflict stops automatic replay for that operation and opens an authorized human-resolution view. No last-write-wins, silent discard, or false “submitted” toast is allowed.
- Device loss/revoked duty/expired session shows a safe recovery path; local sensitive data is encrypted and can be invalidated according to the device/session contract.

### 9.5 Grading and OMR review

- Grading page shows one active version, lifecycle (`open`, `review`, `finalized`), completeness, missing/invalid counts, and source attribution.
- Manual score entry is a complete fallback. Save is transactional across the declared unit; partial multi-area saves are not represented as complete.
- OMR import is staged with row/item states, valid/uncertain/invalid counts, identity binding status, review queue, retry, and resumability. The UI never says “accepted” when it means “proposal received.”
- Finalization and post-finalization correction require explicit prerequisites, reason/version, confirmation, and updated audit evidence. A scanner/model cannot finalize.

### 9.6 Consultation, result preparation, and release

- Consultation decision is visibly human-owned and separate from model/Program Head/counselor suggestions.
- Internal result preview is watermarked and scoped. Generation/printing/distribution evidence is separate from external publication.
- F2F flow shows batch consultation schedule and printed-result status. Online flow can release the approved digital result while consultation remains open through approved availability/reservation.
- Release shows prerequisites, current release version, notification/delivery state, and reversal option only when authorized. Unrelease creates a superseding state; it does not erase history.
- Applicant sees only the currently approved released artifact and a safe history/status summary.

### 9.7 Reports and dashboards

- A report page starts with scope, metric definitions, selected cycle/date range, freshness, and authorization context.
- KPI cards, charts, tables, and exports use the same query snapshot and metric dictionary.
- Demographic/program charts apply suppression/aggregation thresholds. Suppressed is not zero.
- A chart click drills into a server-authorized detail list; the breadcrumb and filter bar show the drill-down context.
- Export controls identify format, scope, row/aggregate class, generation state, and audit status. Error/retry never claims a file exists when generation failed.
- Program Head views are read-only and program-scoped. Super Admin audit views are clearly separate from routine reports.

### 9.8 RAG and bounded inquiry

- Chat opening states the available context: policy, own-data, or staff task assistance.
- Policy answers cite the approved policy and link to it. Unsupported/prohibited questions refuse with a concise reason and safe staff fallback.
- Own-data answers show the release/scope basis without exposing internal policy internals.
- Staff task assistance presents a proposal preview, affected target/scope, command name in human language, validation warnings, and a confirm/cancel step. Confirmation dispatches the ordinary command with an idempotency key.
- Refusal-to-inquiry asks for applicant confirmation before creating a bounded Guidance inquiry. The confirmation states destination, expected response channel, and that creating an inquiry is not an immediate resolution.
- Provider outage shows degraded assistant state and manual page/task alternative. No fabricated citation, task success, or background mutation appears.

## 10. Responsive, device, and print behavior

- The layout is usable from small phone width through desktop without horizontal page overflow. A table may scroll within a labeled region or switch to cards; the user is never trapped by an inaccessible wide table.
- Interactive controls target at least a comfortable touch size (the existing UI uses 44px minimum for key controls); icon-only controls have accessible names and visible focus.
- On mobile, side navigation becomes a focus-managed drawer; breadcrumbs collapse to an accessible ancestor menu; filters become a grouped disclosure with clear apply/reset behavior; primary action remains reachable.
- Sticky headers/actions must not obscure focused content or live regions. Safe-area insets are respected on mobile devices.
- Camera/QR/OMR views state permission, loading, denied, unavailable, switched-camera, and retry states. Camera failure never blocks the manual identifier path unless the approved workflow explicitly requires it.
- Offline/PWA installation is an interface capability only; it does not grant permission for authoritative offline writes. Device storage, encryption, logout, revocation, retention, and purge follow the offline contract.
- Print views remove navigation/toast chrome, preserve required scope/template/version/watermark, avoid exposing secrets, and record protected print evidence when applicable.

## 11. Accessibility and inclusive interaction

The engineering target is WCAG 2.2 AA behavior for the implemented scope, subject to explicit test evidence rather than an unverified compliance claim.

Required behavior:

- keyboard access to navigation, breadcrumbs, filters, dialogs, tables, charts, toasts, forms, and assistant actions;
- visible focus and logical focus order;
- skip link and semantic landmarks (`header`, `nav`, `main`, `footer`);
- labels, descriptions, error associations, and status announcements for every form/control;
- `aria-current` for current breadcrumb/nav; dialog names/descriptions and focus return;
- live regions that do not spam during polling or chart updates;
- contrast and non-color status encoding; patterns/text for chart categories and OMR colors;
- reduced-motion support and no essential information conveyed only through animation/sound;
- chart/graphic text or table alternative and exact values where appropriate;
- captions/transcripts or text equivalents for media/help content;
- responsive zoom/reflow and no loss of action at increased text size;
- language that distinguishes pending, queued, committed, failed, denied, and not-yet-released states.

## 12. Privacy and security presentation rules

- Page props, chart series, tooltip data, notifications, toasts, URLs, browser storage, screenshots/print output, and error messages are subject to the same data-scope rules as the underlying query.
- Do not put Applicant No as if it were a secret, and do not put setup/reset challenges, credentials, raw OMR images, full scores, or sensitive applicant details in URLs or toasts.
- Mask/truncate identifiers in list pages when full identity is unnecessary. Show full values only in an authorized detail context.
- Protected view/export/preview/print actions show a safe scope/purpose indicator and emit audit evidence.
- Break-glass state is persistent and not hidden in the notification center.
- Client local storage may retain only approved non-sensitive preferences (theme, view mode, filter preference). Offline sensitive data requires the separate encrypted device/session contract.
- RAG/chat history clear, assistant context changes, inquiry creation, and provider failure are visible and auditable according to GF-08.

## 13. Frontend verification contract

### 13.1 Component/unit tests

Vitest/component tests must cover:

- breadcrumb current/ancestor/compact/mobile behavior;
- server-authorized navigation filtering and safe active-route matching;
- form validation, focus, dirty-state guard, idempotent submit, and conflict rendering;
- toast severity, dedupe, duration class, dismissal, live-region role, and redaction;
- notification polling/read retry/deduplication;
- table/card/filter/sort/pagination URL-state preservation;
- chart data mapping, units, no-data/zero distinction, suppression, table alternative, responsive/theme/reduced-motion behavior;
- OMR green/uncertain/red and correction-state rendering;
- offline outbox/sync/conflict state rendering where enabled;
- assistant proposal/confirmation/refusal/degraded states.

### 13.2 Connected browser journeys

Playwright journeys must prove at least:

1. public submission → post-submission setup state → Registrar queue;
2. validation/network failure preserves safe form state;
3. Registrar Support Staff cannot navigate/use setup pages;
4. Registrar accept/dismiss confirmation, stale conflict, and success feedback;
5. Test Administrator opens reservation, finalizes/bypasses, publishes, and sees notification state;
6. Proctor assignment/revocation and assigned-session-only access;
7. attendance/submission idempotent retry and effective deadline;
8. manual scoring fallback and OMR uncertain-review path;
9. finalization/release/notification and applicant pre/post-release visibility;
10. report scope, chart/table alternative, filtered drill-down, and export audit state;
11. RAG policy citation, own-data gate, refusal, confirmation, typed task command, and outage fallback;
12. offline pending/replay/conflict/manual-resolution path when promoted;
13. Super Admin break-glass activation, persistent banner, full audit-scope view, expiry, and post-review evidence;
14. keyboard-only and narrow-screen flows for every critical journey.

Every connected journey includes at least one allowed, denied, stale/conflict, provider-degraded, and recovery assertion where the state applies.

### 13.3 Visual/responsive review

The R5 package must include screenshots or equivalent evidence for:

- staff desktop and mobile shell;
- applicant portal and multi-step breadcrumbs;
- a table/card page with filters and empty states;
- dashboard KPI/charts with data, no-data, stale, suppressed, and error variants;
- notification center and each toast severity;
- confirmation dialog and validation summary;
- OMR review workspace and document preview/print;
- offline/reconciliation banner and conflict resolution;
- denied, not-found, and break-glass states.
- operations/recovery views showing hold-protected purge, backup/restore status, audit verification, and terminal job reconciliation states.

Visual review verifies text alternatives, not merely pixel similarity.

## 14. Traceability and promotion conditions

GF-22 must be linked from GF-11 acceptance, GF-12 traceability, GF-14 R5/E2/E3 roadmap, GF-17 browser/container design, GF-18 UI-state expectations, GF-20 page/query/command contracts, and GF-21 authorization enforcement.

Promotion requires:

- every committed workflow has a page/route or explicit non-UI operational representation;
- every mutation has validation, confirmation level, pending/success/failure/conflict feedback, and audit correlation;
- every page has loading, empty, denied, error, and responsive behavior or an explicit statement that a state is impossible;
- every chart/graphic has a metric/source/scope/freshness contract, accessible text/table alternative, privacy handling, and no-data/error behavior;
- every toast/notification has severity, duration/dismissal, deduplication, redaction, and authoritative wording;
- role-aware navigation and controls match GF-21 but backend tests prove the boundary independently;
- offline, OMR, BGE, RAG, scheduling assistance, and inquiry behavior retain their evidence gates and visible fallbacks;
- Vitest and Playwright scenarios are assigned with real discovered tests; no zero-test claim is accepted.

## 15. Open evidence and product decisions

The following remain visible rather than fabricated:

- final institutional wording for admission states and reason taxonomy (EV-015);
- approved privacy/retention/suppression rules for real data and reports (EV-013/EV-017);
- exact OMR sheet/device/threshold corpus (EV-008);
- exact Yukee parameters and model claim (EV-009);
- offline device/connectivity and sync evidence (EV-011);
- approved RAG corpus/questions/fallback contacts (EV-012);
- bounded inquiry thread/routing/SLA/retention model (EV-018);
- final route names, localization/content review, chart library/renderer choice, and design tokens after the component inventory is approved;
- exact institutional notification channels and sound/preference policy.

Until these close, the UI contract uses explicit constrained assumptions and visible fallback states. It does not silently downgrade a committed feature to a fake success, hidden panel, or cache-only implementation.
