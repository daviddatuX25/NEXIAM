# Tasks: Notifications + Delivery Outbox

## A — Open

- [x] A1 Confirm no other active change; archive prior cut (`grading-roster-eligibility` PASS)

## B — Schema

- [ ] B1 Migration: create `notification_deliveries` (unique event/recipient/channel + status/read/retry indexes) and drop placeholder `notification_outbox` (NT-1)
- [ ] B2 `NotificationDelivery` model + `NotificationIntent` value object + `NotificationIntentDispatcher` (in-transaction writer, NT-1/NT-5)

## C — Worker + channels

- [ ] C1 `DeliveryChannel` contract + `InAppDeliveryChannel` + `MailDeliveryChannel` (Mailpit) (NT-3)
- [ ] C2 `ProcessNotificationDelivery` state-machine job (pending→processing→delivered/retrying→failed_terminal, max 5, audit transitions) + `DispatchPendingNotifications` after-commit enqueue (NT-2/NT-3)
- [ ] C3 Register `notify.read.own` in `CapabilityCatalog` + grant to all staff roles + `ApplicantCapabilityGrants` (NT-9)

## D — Emission from existing Actions

- [ ] D1 `NotificationRecipients` support (assigned applicants, proctors + TA set, applicant portal user) (NT-8)
- [ ] D2 Re-point `PublishExamSession` + `ReleaseResult` + `SubmitAdmissionAttempt` from `notification_outbox` to dispatcher (NT-8)
- [ ] D3 Add intents to `AcceptAdmissionAttempt`, `ReserveExamSlot`, `AssignProctorDuty`, `CloseExamSession` (NT-8)

## E — HTTP + projection + UI

- [ ] E1 `NotificationProjection` (own-data bell list + unread count, NT-4/NT-6)
- [ ] E2 `NotificationController` (list / mark-read / read-all / unread-count; own-row gate, NT-4/NT-7) + routes with `notify.read.own`
- [ ] E3 Inertia shared `notifications` props + Svelte `usePoll(30s, only:['notifications'])` (NT-4)
- [ ] E4 `NotificationBell.svelte` mounted in `AuthenticatedLayout` + `ApplicantLayout` (NT-4/NT-7)
- [ ] E5 Truthful toast wording on release/publish/close actions (NT-6); typed severity in `ToastManager`

## F — Proof

- [ ] F1 Pest: unique event/recipient/channel dedupe; replay idempotent commands no-duplicate (NT-1/NT-8)
- [ ] F2 Pest: state machine transitions incl. backoff + failed_terminal; in-app vs mail independence (NT-3)
- [ ] F3 Pest: own-data cap (user B cannot read/mark user A), authz on routes (NT-4/NT-9)
- [ ] F4 Pest: truthfulness — no delivered claim pre-worker; queue wording (NT-6)
- [ ] F5 Pest: mark-read idempotency + mark-all scoped (NT-7)
- [ ] F6 Update `ReleaseFlowTest`/`ExamSlotReservationFlowTest` from `notification_outbox` to `notification_deliveries`
- [ ] F7 Run `tests/Feature/Notifications tests/Feature/Release tests/Feature/Examination tests/Feature/Admission` via Compose
- [ ] F8 Seeder (`NotificationDemoSeeder` or GradingDemoSeeder extension) + HANDOFF demo credentials + recreate steps (§3.7)
- [ ] F9 Evidence note + archive README + project-context/master update on PASS
