# D-ROLE-05 — Program Head metrics, scope, and privacy boundary

**Status:** APPROVED — founder decisions reconciled 2026-08-15
**Closes:** GF-04 D-ROLE-05 · REQ-VS-025 projection hold · REQ-REP-001 metric-dictionary hold (within this bounded scope)
**OpenSpec:** `2026-08-15-admission-model-program-head`

## Decision

### 1. Scope

Program Head is assigned-program scoped. Aggregate and candidate projections may expose only the assigned program/cycle. Candidate identity requires Registrar acceptance; submitted/pre-accept preference candidates are aggregate-only.

Full protected record access follows D-080. PH never receives application Accept/Dismiss, score/release writes, raw sheets/scans, confidential conversion tables, or general cross-program access.

### 2. Metrics

All metrics are active-cycle, program-scoped, derived from committed state, and show source + `as_of`:

| Metric | Definition |
|---|---|
| Preference candidates | deduped attempts with program in application P1–P3 |
| Accepted candidates | preference candidates accepted by Registrar |
| Exam completed candidates | accepted candidates with completed attendance + submission |
| Result released candidates | candidates with current released result |
| Advised candidates | candidates with confirmed consultation decision |
| Guidance-pool candidates | attempts with active Guidance option for program |
| Active reservations | active + pending-acknowledgement program selections |
| Upcoming | active acknowledged selection + one remaining option + no follow-up |
| Open follow-ups | unresolved targeted follow-ups |
| Enrolling / Enrolled | Registrar stamp counts for selected program |
| Quota | active reservations / slots; remaining = max(slots - reservations, 0) |

**Quota fill counts reservations, not accepted applicants.** Preferences and pool membership consume no quota.

### 3. Privacy decision

No small-cell suppression: the dashboard is authenticated assigned-program staff-only, default-deny, and protected views are audited. This is the approved REQ-VS-025 threshold decision. It does not permit identified pre-accept rows or broaden protected record access.

### 4. Presentation

Potential rows show basis tags (`preference #N`, `guidance pool`) and pipeline tags. Accessible table alternatives distinguish zero, no data, stale, denied-by-scope, and error. Charts cannot mutate state.

## Downstream authority

GF-04 CAP-024/036, GF-06 §12, GF-12 REQ-REP-001, PRD FR-036–038, and page P-PH-01 must follow this record.
