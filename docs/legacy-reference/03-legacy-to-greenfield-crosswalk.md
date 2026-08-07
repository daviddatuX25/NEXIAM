# 03 — Legacy-to-Greenfield Crosswalk

**Purpose:** make replication traceable from old source to target contract and future implementation

## Crosswalk

| Legacy source | Observed responsibility | Greenfield target contract | Target module/shape | Reuse/disposition |
|---|---|---|---|---|
| `legacy_codebase/application/app/Http/Controllers/ApplicationController.php` | Public/staff application request orchestration | GF-05, GF-06, GF-18, GF-20, GF-21, GF-22 | `Admissions` page adapters plus `Submit/Correct/Accept/Dismiss` commands | Reuse fields and journey evidence; extract behavior into target commands and projections |
| `legacy_codebase/application/app/Services/ApplicationPipelineService.php` | Dashboard/pipeline stage composition | GF-05, GF-06, GF-18 | Rebuildable admissions projection/query | Reuse projection needs; let the target own one command-owned state machine |
| `legacy_codebase/application/app/Http/Controllers/Portal/` | Applicant status, access, notifications, AI | GF-08, GF-20, GF-21, GF-22 | Applicant shell, own-data queries, credential commands, bounded assistant adapter | Preserve journey intent; rewrite auth/scope/provider boundary |
| `legacy_codebase/application/app/Http/Controllers/Admin/ExamSessionController.php` | Session creation/edit/lifecycle | GF-06, GF-17, GF-18, GF-19, GF-20 | `Examination` commands and session read models | Rewrite locks, deadline, publication, and authority |
| `legacy_codebase/application/app/Services/ExamSchedulingService.php` | Scheduling and assignments | GF-05, GF-06, GF-18, GF-20 | Slot-plan/reservation/assignment application services | Preserve scheduling vocabulary; redesign capacity and replay |
| `legacy_codebase/application/app/Http/Controllers/Proctor/` | Assigned roster/attendance/session operations | GF-04, GF-06, GF-21, GF-22 | Proctor duty policy plus session commands | Reuse the contextual workflow; model Proctor as an assignment-scoped duty |
| `legacy_codebase/application/app/Http/Controllers/Grading/` | Grading session and score entry | GF-05, GF-06, GF-18, GF-19, GF-20 | `Assessment` commands, versioned scores, review/finalize states | Preserve manual workflow; rebuild state/cardinality |
| `legacy_codebase/application/app/Services/*Import*` and OMR artifacts | File import and proposed score ingestion | GF-07, GF-10, GF-18, GF-20 | Staged `ScoreImportBatch/Row`, reviewed proposal adapter | No direct model/provider write to score authority |
| `legacy_codebase/application/app/Http/Controllers/Release/` | Result release and print preparation | GF-06, GF-08, GF-18, GF-20, GF-22 | `ConsultationRelease` commands and document operations | Separate preparation/release/reversal/notification |
| `legacy_codebase/application/app/Services/PrintBatchService.php` | Bulk document generation | GF-07, GF-18, GF-20, GF-23 | Private artifact operation with checksum/version/status | Preserve layouts only after trust-boundary review |
| `legacy_codebase/application/app/Notifications/` | Notification content and event intent | GF-07, GF-20, GF-22, GF-23 | Durable notification projection/delivery jobs | Preserve content intent; make delivery truthful/idempotent |
| `legacy_codebase/application/app/Policies/` and route middleware | Authorization checks | GF-04, GF-08, GF-21 | Capability/resource/context policy pipeline | Reuse tested allow/deny scenarios; express authority through capabilities, scope, and duty context |
| `legacy_codebase/application/resources/js/Layouts/` | Staff/applicant layout shells | GF-22 | Staff/applicant/proctor/break-glass shells | Preserve affordances; target page props/state/accessibility |
| `legacy_codebase/application/resources/js/Pages/` | Page journeys and interaction states | GF-11, GF-22 | Target page contracts and Playwright flows | Reuse testable behavior and states; rebuild props/markup where the target contract requires it |
| `legacy_codebase/application/database/migrations/` | Legacy schema evolution | GF-19 | Fresh PostgreSQL migration history | Reuse fields, relationships, and constraint discoveries; establish one fresh target history |
| `legacy_codebase/application/tests/` and historical verification docs | Intended test cases | GF-11, GF-12, GF-24 | Target PHPUnit/Vitest/Playwright/evidence matrix | Reuse scenarios and fixtures after target semantics are settled |

## Required trace tuple

Every implemented target capability should eventually be traceable as:

```text
legacy source
  → observed behavior
  → disposition
  → founder decision / evidence
  → GF contract
  → OpenSpec requirement
  → target module/command/query/page
  → executable test or runtime evidence
```

## Implementation review checklist

Before recreating a legacy feature, verify:

- Is the actor authority still valid under GF-04/GF-21?
- Is the old persisted state the same as the target state, or only a display projection?
- Does the old behavior have a safe retry/idempotency/conflict story?
- Does the UI expose loading, denied, empty, conflict, degraded, offline, and queued states from GF-22?
- Does the operation create the required audit evidence?
- Is the behavior in E2, or should it be a spike/deferred capability?
- Is there a more authoritative institutional/panel/manuscript source than the code?

## E2 slice detail

Per-`REQ-VS-*` Legacy → Target Trace rows: [`08-e2-connected-slice-trace.md`](08-e2-connected-slice-trace.md).
