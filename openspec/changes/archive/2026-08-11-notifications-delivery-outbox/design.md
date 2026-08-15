# Design — Notifications + Delivery Outbox

**OpenSpec:** `notifications-delivery-outbox`
**Authority:** GF-19 §3.7 (`notification_deliveries`), GF-22 §5.5 (§ notifications center) + §6 (toast contract), GF-21 (own-data scope), GF-07 FAIL-030, master §6.5. Legacy UX basis: `PortalNotificationController`, `NotificationController`, `Notifications.svelte`-style bell behaviors.

## Domain model (new table)

### `notification_deliveries`

Per GF-19 §3.7: "Event/recipient/channel delivery and read state — Unique event/recipient/channel; status/read/retry indexes".

| Column | Type | Notes |
|---|---|---|
| `id` | uuid pk | |
| `event_id` | uuid | dedupe identity for one logical domain event occurrence |
| `event_type` | string(100) | canonical `{domain}.{event}.v1` contract key |
| `event_version` | smallint | event contract version (v1 = 1); payload contract identity |
| `recipient_type` | string(50) | always `App\Models\User` this cut |
| `recipient_id` | bigint | `users.id` (staff + applicant portal user) |
| `channel` | string(16) | `in_app` \| `mail` |
| `template_key` | string(100) | immutable content template reference, e.g. `exam.session_published` |
| `template_version` | smallint | append-only content version frozen at dispatch (0 → n) |
| `payload` | jsonb | frozen payload at dispatch time (template variables) |
| `redaction_class` | string(50) null | future redaction classification (reserved) |
| `status` | string(24) | `pending` \| `processing` \| `delivered` \| `retrying` \| `failed_terminal` |
| `attempts` | smallint default 0 | delivery attempts so far |
| `next_attempt_at` | timestamptz null | backoff scheduling for `retrying` |
| `last_error` | text null | last adapter error (redacted, no PII) |
| `provider_reference` | string(255) null | external reference (e.g. mail message id) |
| `read_at` | timestamptz null | user marked read |
| `seen_at` | timestamptz null | projection has surfaced it |
| `created_at` / `updated_at` | | |

**Constraints/indexes**
- unique `(event_id, recipient_type, recipient_id, channel)` — dedupe; identical event→same user→same channel can exist only once.
- index `(recipient_type, recipient_id, status)` — bell list + unread count.
- index `(status, next_attempt_at)` — worker claim scan.
- index `(created_at desc)` partial where `read_at is null` — unread badge count.

### Schema migration plan

- New migration creates `notification_deliveries`.
- **Drop the placeholder `notification_outbox`** in the same migration (it was E2-scaffold-only; no production data). Update `ReleaseFlowTest`/`ExamSlotReservationFlowTest` assertions to `notification_deliveries`.

## Delivery state machine

```text
pending ──(worker claim)──▶ processing ──(channel ok)──▶ delivered
              │                       │
              │                       └─(channel fails, attempts < max)─▶ retrying ──▶ (backoff → pending again)
              │                       └─(channel fails, attempts ≥ max)─▶ failed_terminal
```

- `in_app` delivery: row IS the durable notification; worker marks `delivered` after the in-app adapter persists the projection (no external call).
- `mail` delivery: worker sends via the mail adapter (Mailpit); success → `delivered` + `provider_reference`; failure → `retrying` (exponential backoff, max 5 attempts) → `failed_terminal` + `last_error`.
- Transitions are audit-logged (`notification.delivery_delivered`, `notification.delivery_failed`) via `RecordAuditEvent`.

## Emission path (after-commit)

1. Domain Action runs inside its `DB::transaction`.
2. Inside the transaction, the Action calls `NotificationIntentDispatcher::dispatch(NotificationIntent $intent, array $recipients)` which writes `notification_deliveries` rows (unique upsert) — atomic with the domain state.
3. After the transaction commits, a `DispatchPendingNotifications` job is enqueued (queue `after_commit=true` already set) for each new delivery id → the worker processes `pending` → … → `delivered`/`failed_terminal`.

Reused Actions and their intents this cut:

| Action | Event contract | Recipients | Channels |
|---|---|---|---|
| `SubmitAdmissionAttempt` | `admission.submitted.v1` | applicant email → portal `User` (when present) | `mail` |
| `AcceptAdmissionAttempt` | `admission.accepted.v1` | applicant portal `User` | `in_app` |
| `PublishExamSession` | `exam.session_published.v1` | assigned applicants' portal `User`s | `in_app` + `mail` |
| `ReserveExamSlot` | `exam.slot_reserved.v1` | applicant portal `User` | `in_app` |
| `AssignProctorDuty` | `exam.proctor_duty_assigned.v1` | proctor `User` | `in_app` |
| `CloseExamSession` | `exam.session_completed.v1` | proctors + Test Administrator set (capability-based) | `in_app` |
| `ReleaseResult` | `result.released.v1` | applicant portal `User` | `in_app` + `mail` |

Recipient derivation lives in `Support` classes (`NotificationRecipients`), never inline in controllers.

## Template versioning

- `template_key` + `template_version` identify the immutable content snapshot. A delivery always stores the version that was current at dispatch; later template edits never rewrite existing deliveries (append-only).
- `event_type` carries the event contract version (`result.released.v1`) so consumers can branch on contract shape without touching template versions.

## HTTP surface

| Route | Method | Capability | Behavior |
|---|---|---|---|
| `/notifications` | GET | `notify.read.own` | Bell list projection (own rows only) |
| `/notifications/{delivery}/read` | POST | `notify.read.own` | Mark one read (idempotent; own-row gate) |
| `/notifications/read-all` | POST | `notify.read.own` | Mark all own unread read |
| `/notifications/unread-count` | GET | `notify.read.own` | Lightweight unread badge count (poll target) |

- `notify.read.own` capability added to `CapabilityCatalog` and granted to **all** staff roles + applicant portal users (`ApplicantCapabilityGrants`) — the bell is own-data, not a role-scoped surface.
- Inertia shares `notifications` props (`unread_count`, recent list) via `HandleInertiaRequests`; Svelte `usePoll(30_000, () => ({ only: ['notifications'] }))` refreshes without full navigation.
- `NotificationBell.svelte` kit component mounted in both `AuthenticatedLayout` (staff) and `ApplicantLayout` headers.

## Truthful toasts (GF-22 §6)

| Outcome | Wording |
|---|---|
| Domain commit succeeded | `"Release accepted."` + in-page state (authoritative) |
| Delivery queued for outbox | `"Notification queued."` (never "email sent") |
| Provider delivered | Only shown when the delivery row is `delivered` (via bell unread), not from a flash |
| Provider failed | `"Notification delivery failed; see bell."` — bell retains `failed_terminal` row with retry/reconciliation seam |

Toast manager (`ToastManager.svelte`) gains typed severity mapping; existing flash bridge stays.

## Files (target `system/`)

- `app/Notifications/Models/NotificationDelivery.php`
- `app/Notifications/NotificationIntent.php` (value object: event_type/version, template_key/version, payload)
- `app/Notifications/NotificationIntentDispatcher.php` (writes rows inside caller transaction; returns new ids)
- `app/Notifications/NotificationRecipients.php` (per-entity recipient derivation)
- `app/Notifications/Contracts/DeliveryChannel.php` + `app/Notifications/Channels/InAppDeliveryChannel.php` + `MailDeliveryChannel.php`
- `app/Jobs/Notifications/DispatchPendingNotifications.php` (after-commit enqueue) + `ProcessNotificationDelivery.php` (state machine worker)
- `app/Http/Controllers/Notifications/NotificationController.php` + `app/Http/Requests/Notifications/…` as needed
- `app/Notifications/NotificationProjection.php` (bell list + unread count, own-data)
- Migration `create_notification_deliveries_and_drop_outbox`
- `resources/js/Components/kit/NotificationBell.svelte`
- Seed: extend `GradingDemoSeeder` or new `NotificationDemoSeeder`
