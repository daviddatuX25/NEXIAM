# 05 — Data and Migration Safety Notes

**Status:** mapping guidance; not a production migration plan

## 1. Hard boundary

The 86 legacy migrations under `legacy_codebase/application/database/migrations/` are historical schema evolution. They are not the greenfield schema history.

The greenfield database is a fresh PostgreSQL authority designed from GF-19. Legacy data import, if required later, is a separate bounded project after the target aggregates, retention rules, identifiers, and reconciliation evidence are stable.

## 2. Mapping domains

| Legacy evidence domain | Greenfield mapping concern | Required decision/evidence |
|---|---|---|
| users/roles/permissions | StaffAccount, capabilities, office/program scopes, contextual duties | Which accounts are real, disabled, service, or synthetic; who owns grants? |
| applicants/applications | ApplicantIdentity and cycle-scoped AdmissionAttempt | How are duplicates, retakes, corrections, and missing references reconciled? |
| academic years/courses/rooms | AcademicCycle, ApplicationWindow, Program, Room | Which records are historical, active, deactivated, or ambiguous? |
| exam sessions/assignments/attendance | ExamSession, ExamAssignment, ProctorDuty, AttendanceRecord | How do old assignments map to accepted attempts and duty scope? |
| scores/grading sessions | GradingSession, ScoreRecord, version/correction/provenance | Which score is authoritative, and can the source be proven? |
| consultation/results/releases | ConsultationDecision, ResultRelease, GeneratedDocument | What was actually released, to whom, under which template/version? |
| notifications | NotificationEvent/Delivery/read projection | Which messages are evidence only versus legally/operationally relevant? |
| audit logs | AuditEvent and verification history | Can integrity, actor, scope, timestamp, and redaction be trusted? |
| knowledge/AI/provider data | KnowledgeDocument/AssistantExchange/proposal artifacts | Is there provenance, privacy clearance, and approved scope? |

## 3. Required import record

A future import mapping must specify:

- source table/field and source snapshot identifier;
- target aggregate/field and transformation;
- normalization and timezone rules;
- unknown/null/invalid behavior;
- duplicate/collision behavior;
- source evidence confidence;
- irreversible information loss;
- retention/legal-hold treatment;
- audit event and operator identity;
- reconciliation count and exception list;
- rollback/quarantine procedure.

## 4. What must not happen

- Do not point greenfield E0/E1/E2 at the legacy database.
- Do not make legacy numeric IDs the target public identifier without an approved mapping.
- Do not import passwords, token material, provider secrets, or raw credentials.
- Do not treat a legacy status column as the target state machine without reconciliation.
- Do not overwrite a target record silently when source rows collide.
- Do not use an import to bypass target authorization, audit, retention, or document provenance.
