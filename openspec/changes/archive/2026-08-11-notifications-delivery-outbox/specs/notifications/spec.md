# Spec — Notifications + Delivery Outbox

**Spec ID:** `notifications`
**Change:** `notifications-delivery-outbox`

## Requirements

### NT-1 — Durable delivery outbox row per event/recipient/channel

- Each logical domain event produces one `notification_deliveries` row per `(recipient, channel)`.
- Unique `(event_id, recipient_type, recipient_id, channel)` prevents duplicate delivery for the same event occurrence.
- Rows are written inside the domain transaction (atomic with the domain state).

### NT-2 — After-commit processing

- Delivery worker jobs are dispatched after the transaction commits (`queue.after_commit=true`).
- No toast or HTTP response claims provider delivery before the delivery row reaches `delivered`.

### NT-3 — Delivery state machine

- `pending → processing → delivered` on success.
- Channel failure: `processing → retrying → pending` (backoff), and after max attempts (5) → `failed_terminal` with `last_error`.
- `in_app` and `mail` adapters both flow through the same worker; a failure in one channel never blocks the other.

### NT-4 — Own-data bell projection (GF-22 §5.5, FAIL-030)

- The bell list and unread count return only rows where `recipient_type = App\Models\User` and `recipient_id = auth()->id()`.
- A user cannot read, list, or mark another user's rows (403 / scoped query).

### NT-5 — Versioned, immutable content

- Each delivery stores `template_key`, `template_version`, and the frozen `payload`.
- Template edits are append-only (version bumps); existing deliveries are never rewritten.

### NT-6 — Truthful toasts (GF-22 §6.2, REQ-VS-013)

- "Notification queued" is the maximum claim at commit time.
- "Sent"/"delivered" wording appears only backed by an authoritative `delivered` delivery row.
- Provider failure surfaces a `failed_terminal` row in the bell with a retry/reconciliation seam.

### NT-7 — Mark-read is idempotent and scoped

- Marking an already-read row is a no-op (returns success).
- Mark-all affects only the current user's own unread rows.
- Read-state failures show visible feedback (GF-22 §5.5 explicit retry).

### NT-8 — Emission from existing Actions

- `SubmitAdmissionAttempt`, `AcceptAdmissionAttempt`, `PublishExamSession`, `ReserveExamSlot`, `AssignProctorDuty`, `CloseExamSession`, and `ReleaseResult` emit through the shared outbox writer with the intents in the design table.
- Replayed idempotent commands do not duplicate deliveries.

### NT-9 — Capability gate

- All `/notifications*` routes require the `notify.read.own` capability (granted to all staff roles + applicant portal users).
- No role strings are used in authorization.

## Non-goals (this spec)

- Scheduled exam-reminder cron (follow-on).
- WebSockets/Reverb (HTTP polling this cut).
- Full inbox page, per-user preferences, Nexi-triggered emission (follow-ons).
