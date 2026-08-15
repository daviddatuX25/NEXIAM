# D-090 — Program candidacy, applicant choice, quota reservation, and enrollee lists

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Creates:** Potential / Upcoming enrollee projections and student-directed quota reservation
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### 1. Preferences remain live evidence

Ordered application preferences P1–P3 remain Potential-list evidence throughout the admission-support journey. They never reserve quota and never, by themselves, expose a protected PH record.

### 2. Guidance owns the allowed pool

After result release + confirmed advice, Guidance finalizes the permitted pool as an immutable complete version and chooses one advised default from it. The initial `SetGuidanceProgramPool` transaction appends pool/options plus a separate pending default selection and FCFS reservation; proposal output may prefill but never choose. Later add/remove/reintroduce appends a reasoned version, preserves attributable preferences/history, and retains any non-terminal selection unless explicitly transferred/cancelled.

### 3. Pre-enrollment selection

The institution's normal route therefore begins with Guidance's advised `guidance_default`; the applicant may acknowledge it or choose another pool option before `enrolling`. Both are provisional pre-enrollment selections under the same FCFS/idempotent quota guard; no PH approval is required.

The Guidance default remains `pending_acknowledgement` until the applicant confirms it through the portal or at an attributable staff counter interaction. Guidance may record in-person acknowledgement, and Registrar may record the applicant's confirmation from the presented enrollment sheet while atomically starting `enrolling`. Staff presence alone is not acknowledgement. A pending default never auto-acknowledges or auto-expires; Guidance may reasonedly cancel only its still-unacknowledged default before `enrolling`.

Selection statuses:

- `pending_acknowledgement`
- `active`
- `superseded`
- `program_declined`
- `cancelled`
- `paper_finalized`

Only one non-terminal pre-enrollment selection per attempt. Applicant change transfers quota atomically and retains history. A pool version cannot silently move/cancel a selection. After `enrolling`, ordinary applicant/Guidance cancellation or transfer is blocked; only resolution of an already-open PH follow-up and Registrar's paper-confirmed final-program adjustment may change/release the provisional reservation. `enrolled` freezes further correction until ordered Registrar reversal (D-091).

### 4. PH lists

| List | Membership |
|---|---|
| Potential | program in P1–P3 or active Guidance pool; not committed to a single different program and not in a later primary list for this program; identified rows only after Registrar acceptance; basis + stage tags shown |
| Upcoming | active acknowledged selection = program; one active option remains; no open follow-up; not yet enrolling/enrolled |
| Follow-up work queue | targeted unresolved follow-up for program; may overlap an `Enrolling` primary status so PH work is never hidden |
| Enrolling / Enrolled | current Registrar stamp for the provisional/final program recorded by that stamp |

Primary lifecycle precedence is `Enrolled → Enrolling → Upcoming → Potential`. Follow-up is an orthogonal work flag/queue, not a mutually exclusive lifecycle bucket: an enrolling applicant with an unresolved edge case appears as `Enrolling` and in the assigned PH follow-up queue.

### 5. Quota

Preferences/pool/follow-up consume zero. Before final paper confirmation, `pending_acknowledgement` + `active` selections reserve capacity. Quota commitment deduplicates each attempt across its current reservation and current `enrolling`/`enrolled` stamp. Ordinary selection fails closed when no active current-cycle quota exists or slots are zero; lowering quota below commitments is rejected. Paper-confirmed enrollment is a record-of-truth exception: it releases a superseded reservation, records the final program even if unconfigured/full, never silently raises capacity, and projects `remaining = max(capacity - commitments, 0)` plus `over_capacity = max(commitments - capacity, 0)`.

### 6. Reconsideration

Before `enrolling`, PH decline of an acknowledged/follow-up-targeted selection or applicant change makes the old selection terminal, releases its slot, and opens reconsideration. During `enrolling`, only an already-open PH follow-up may resolve/decline the provisional choice; Registrar later confirms or adjusts the final program from the signed enrollment sheet. Guidance may supersede the pool for pre-enrollment reconsideration, but the paper-confirmed final program is not forced through the Guidance pool.

### 7. Paper-confirmed final program

At `enrolled`, Registrar records the signed enrollment sheet's program as the final institutional fact. It may differ from the Guidance pool/default or applicant's provisional selection and may exceed configured quota. The transition preserves the old selection/pool/PH history, records verifier + paper reference/hash + reason/source, moves/releases the provisional commitment atomically, and exposes any quota variance for Program Head reporting.

### 8. No autonomous placement

Provider suggestions can inform Guidance, but consultation decision, suggestion review, pool, and pre-enrollment selection remain separate records/actions: only explicit counselor `SetGuidanceProgramPool` defines the pool; only applicant action/attributable acknowledgement establishes a standing pre-enrollment selection; and only Registrar verification of the signed external enrollment sheet establishes the final enrolled program.
