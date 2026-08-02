# Catch-up and Continue Progress

**Artifact:** NEXIAM-GF-00  
**Status:** ACTIVE PROJECT NAVIGATOR — FOUNDATION REVIEW PENDING  
**Last verified:** 2026-08-02  
**Purpose:** Give David a reliable return point after a break so the project can resume without rereading the entire repository or confusing historical evidence with approved implementation authority.

## 1. Current position

NEXIAM is at the greenfield foundation and founder-review checkpoint.

Completed:

- The greenfield foundation suite has been drafted in `docs/greenfield-foundation/`.
- The suite contains the recovery/authority contract, evidence register, scope, roles, domain model, workflows, failure/recovery, security, platform baseline, technical validation, vertical-slice acceptance, traceability, decision matrix, readiness roadmap, ADR catalog, and diagrams.
- The suite has been structurally validated: 80 canonical decisions, 59 semantic aliases, 18 evidence entries, 33 contradictions, 35 capability rows, 77 requirements, 22 vertical-slice scenarios, 15 ADRs, 2 Mermaid sources, and 2 rendered SVG diagrams.
- The private NEXIAM repository contains the authoritative draft suite.
- A separate public documentation site exists only for reading the foundation artifacts; it is not the NEXIAM implementation repository.

Not yet approved:

- The decision matrix has not received the founder's final dispositions.
- P0 authority, role, identity, authentication, data, workflow, release, document, reporting, and boundary decisions remain review items.
- No greenfield schema, authentication implementation, or domain implementation should begin from the draft suite yet.
- The existing worktree is dirty and must not be treated as a clean greenfield baseline.

Current verdict:

> Planning coherence: DRAFT/PREPARED. Implementation preparation: BLOCKED pending P0 approval and propagation. Pilot/production clearance: NOT GRANTED.

## 2. Read this first when returning

Use this order for a normal catch-up:

1. This file — current position and next actions.
2. `README.md` — authority model and complete suite map.
3. `13-decision-and-batch-approval-matrix.md` — the decisions David must approve, revise, condition, or reject.
4. `11-connected-vertical-slice-and-acceptance.md` — the minimum defensible working version.
5. `14-readiness-gates-and-reconciled-roadmap.md` — the conditions and sequence for implementation.

Do not begin by reading legacy code. Legacy code is evidence and may explain a contradiction, but it is not greenfield authority.

## 3. Founder/PM catch-up checklist

The founder/PM review should produce one disposition for every decision in GF-13:

- `APPROVE` — accept the recommended default.
- `APPROVE WITH CONDITION` — accept only with a written constraint or validation task.
- `REVISE` — replace the recommendation with a specific alternative.
- `REJECT/DEFER` — remove it from the committed slice or move it to a named later phase.

Prioritize the dependency roots first:

1. Authority, evidence precedence, and greenfield boundary.
2. Product cut-line and minimum capstone promise.
3. Role catalog, contextual duties, and office/program/department scope.
4. Applicant identity, account activation, duplicate handling, and authentication/recovery.
5. Application, exam, grading, consultation, release, and document state ownership.
6. Database/runtime/test stack and clean-workspace policy.
7. Vertical-slice acceptance and Go/No-Go conditions.
8. Conditional differentiators: OMR, RAG, inference, reporting, messaging, and offline operation.

The review is not complete when a row is merely discussed. It is complete when the disposition, owner, evidence requirement, and downstream impact are recorded.

## 4. PM next actions after review

After the decision matrix is reviewed:

1. Record the disposition of D-001 through D-080.
2. Identify every changed recommendation and the affected documents.
3. Close or explicitly bound all P0 contradictions.
4. Confirm the committed, conditional, and deferred scope cut-lines.
5. Confirm the first connected vertical slice and its acceptance evidence.
6. Issue a formal planning verdict: PASS, CONDITIONAL PASS, or BLOCKED.
7. Approve the next OpenSpec/BMAD planning package only after the verdict is recorded.

PM must not silently resolve an unanswered founder or stakeholder choice inside an implementation ticket.

## 5. Lead Engineer next actions after approval

The Lead Engineer should work only from the promoted decision chain:

1. Create a decision disposition log and update GF-01/GF-02/GF-13.
2. Propagate approved decisions through scope, roles, workflows, domain, security, traceability, and roadmap documents.
3. Promote the affected ADRs from GF-15 in dependency order.
4. Produce the approved PRD, architecture contract, schema/ERD contract, and executable OpenSpec package.
5. Establish a clean greenfield workspace separate from the dirty legacy worktree.
6. Build E0: scaffold, runtime, Compose topology, quality gates, migration skeleton, and deterministic seed path.
7. Build E1: identity, account activation, authorization scopes, reference data, and audit foundation.
8. Build E2: the connected admission-to-release slice using manual scoring as the complete fallback.
9. Verify every slice through browser, authorization-denial, concurrency, recovery, audit, document, notification, and degraded-provider evidence.

No code should be called canonical merely because it exists. A competing implementation must be removed, explicitly retained as an adapter, or marked historical before the slice is promoted.

## 6. What the first working version must prove

The minimum defensible vertical slice is:

`public or staff intake → ApplicantIdentity/AdmissionAttempt → Applicant No/Reference + post-submission access challenge → authorized review → acceptance/dismissal as an independent Registrar decision → scheduling → exam assignment → attendance/proctoring → manual grading → consultation/release → applicant retrieval → notification/document/audit verification`

The slice must also prove:

- unauthorized cross-scope access is denied;
- duplicate and concurrent commands have deterministic outcomes;
- manual scoring remains complete when optional providers fail;
- corrections and reversals preserve history;
- documents remain private and release-gated;
- notifications are after-commit and idempotent;
- audit evidence detects tampering or missing sequence events;
- the environment can be reset and reproduced.

## 7. Explicit stop boundaries

Stop and return to the decision matrix if any of the following occurs:

- a requirement contradicts an approved decision;
- a role or scope is inferred from a legacy route without a target approval;
- an identity or state transition is introduced without an owner and invariant;
- an optional AI/OMR/provider result is treated as authoritative without human review;
- a report, document, notification, or export exposes data outside its approved scope;
- implementation begins in the dirty legacy worktree as though it were the greenfield baseline;
- a technical spike is described as passed without reproducible evidence.

## 8. Continue-progress checkpoint template

Copy this section into the next project note or progress update:

```text
Checkpoint date:
Current phase: R1/R2/R3/R4/R5/E0/E1/E2/E3/E4/E5/E6
Completed since last checkpoint:
Founder decisions resolved:
P0 decisions still open:
Documents requiring propagation:
Implementation work allowed:
Implementation work still blocked:
Evidence produced:
Risks or contradictions discovered:
Next PM action:
Next Lead Engineer action:
Go/No-Go status:
```

## 9. Single-sentence continuation rule

> Continue from the latest approved decision and verified checkpoint; never continue from memory, an unreviewed draft, or a legacy implementation assumption.
