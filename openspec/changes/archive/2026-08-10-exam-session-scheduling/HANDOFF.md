# Handoff: Exam Session Scheduling

**Date:** 2026-08-08  
**Active OpenSpec:** `openspec/changes/exam-session-scheduling/`  
**Checkpoint:** **Option A shipped** — session `booking_mode`; reserve = assignment; SlotPlan removed

## Founder direction (2026-08-08, Option A)

Too many hops (create sessions → plan → link → open → reserve hold → close → finalize → publish).  

**Simplify:**

- Default: staff-only assign after session create  
- Per-session **Open / Close reservation**  
- Applicant **Reserve** creates `ExamAssignment` immediately (`applicant_reserved`)  
- No SlotPlan UI / soft-hold / finalize  
- Publish remains notify boundary  
- DB reset allowed  

Authority: `docs/greenfield-foundation/decisions/D-088-exam-scheduling-conflict-resolutions.md` (amended).

## Key code map

| Area | Paths |
|---|---|
| Session pages | `Examination/{Index,Create,Edit,Show}.svelte` |
| Applicant | `Applicant/Schedule.svelte` (+ pipeline) |
| Actions | Open/CloseExamSessionReservation, ReserveExamSlot → Assignment |
| Models | ExamSession (+ booking_mode), ExamAssignment |
| Routes | `exams.sessions.*` only (no slot-plans) |

## Developer-ready seeded data (required)

Per `docs/project-context.md` §3.7 — human click-through without inventing fixtures.

### App URL

- `http://127.0.0.1:18000` (Compose `app`)

### Logins

| Actor | How | Credentials |
|---|---|---|
| Guidance (exam schedule) | Staff `/login` | `guidance.counselor@nexiam.test` / `password` |
| Registrar (accept apps) | Staff `/login` | `registrar.clerk@nexiam.test` / `password` |
| Accepted applicant | Applicant portal | access key `DEMO-ACPT` · birthdate `2003-09-25` |
| Pending applicant | Applicant portal | access key `DEMO-PEND` · birthdate `2003-09-25` |

Config source: `system/config/seed.php` → `JourneyFixtureSeeder` + `StaffIdentitySeeder`.

### Recreate (clean DB)

```bash
cd system
docker compose build app
docker compose up -d --force-recreate app queue
docker compose exec -T app php artisan foundation:reset --force
```

`foundation:reset --force` already runs `JourneyFixtureSeeder` (demo applicants). To reseed demos only:

```bash
docker compose exec -T app php artisan db:seed --class=JourneyFixtureSeeder --force
```

### Feature URLs

| Surface | Path |
|---|---|
| Staff exam sessions | `/exams/sessions` |
| Create session | `/exams/sessions/create` |
| Session show (Open/Close reservation, roster, publish) | `/exams/sessions/{id}` |
| Applicant schedule pipeline | `/applicant/schedule` |

### Expected starting state after reset

- No exam sessions yet (staff-only create from Index).
- `DEMO-ACPT` is accepted / exam-eligible → applicant schedule shows **waiting_for_reservation_open** until a session is opened for reservation.
- `DEMO-PEND` is not accepted → schedule stays **pending_acceptance**.

### Minimal happy-path click script

1. Guidance: create a draft session (room + times) → Show.
2. Optionally assign `DEMO-ACPT` (staff-only path) **or** click **Open reservation**.
3. Applicant `DEMO-ACPT`: `/applicant/schedule` → calendar → confirm seat → assignment (`applicant_reserved`).
4. Guidance: Publish when roster ready (notify boundary).

### Automated proof

```bash
docker compose exec -T app php artisan test --compact tests/Feature/Examination
```

Evidence: `docs/greenfield-foundation/evidence/exam-session-scheduling-option-a-evidence.md`.

## Deferred (not in this cut)

- **Applicant notify** on staff Remove / Reassign / Cancel session (NotificationOutbox seam; publish remains the only wired notify today).
- Richer applicant nav beyond single status home.

## Next after this OpenSpec

AI assistant foundation (out of this cut).
