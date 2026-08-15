# Handoff — Admission Support, Program Choice, and Program Head Interface

**Status:** APPROVED QUEUED PLAN — implementation and demo data do not exist yet.

## Authority and start gate

- Change: `2026-08-15-admission-model-program-head`
- Decisions: D-087, D-ROLE-05, D-080, D-089, D-090, D-091, D-092, D-093
- The OMR work owns the sole active implementation change and `DatabaseSeeder`.
- Do not edit `system/` for this change until the OMR owner hands off and this change is promoted as the one active implementation OpenSpec.
- First implementation task: Phase 0 capability rows/tests in `tasks.md`; then phases 1–9 in order.

## Current runnable state

There are no feature routes, credentials, seed scenarios, migrations, or commands for this change. Do not present existing generic users or consultation demo rows as proof of this feature.

## Required implementation handoff contract

Before this change can be marked complete, replace this planning state with observed evidence containing:

1. Exact Compose reset command plus direct `AdmissionSupportDemoSeeder` command and observed idempotent-rerun result.
2. Credentials for Registrar, Guidance/TA, assigned BSIT PH, cross-scope PH, self-service applicant, assisted/default applicant, and reconsideration/follow-up applicant.
3. Exact URLs for the Guidance consultation workspace with embedded score charts/table and human pool/default controls, applicant program choice/access history/My QR, PH program index/dashboard/candidate, generic staff scanner/action hub, and Registrar application/eligibility/stamps.
4. Seeded starting states for normal Guidance default, pending/default confirmed at Registrar, sheet-received Enrolling, Enrolling + open PH follow-up, PH clear/decline/request-appearance, signed-paper final-program adjustment, full/unconfigured quota variance, Potential/Upcoming/Enrolled, message unread/read, protected-view notice dedupe, released-score visualization, no-analysis, and valid/expired/no-destination universal QR.
5. Targeted Pest/Vitest evidence, chart/table parity and source/freshness proof, same-QR Registrar/Guidance/PH action-menu isolation, Pint/PHPStan result, Chromium paths/screens/states, and fresh full-suite result or the exact pre-existing blocker with ownership.
6. Explicit confirmation that no raw sheet/scan/conversion table, unrelated counselor note, cross-program record, silent quota reservation, QR authority/destination, YuKee/deterministic/BGE provider claim, fake engine, or broader enrollment record is exposed.

## Locked workflow shorthand

`Registrar accept → exam → result release → advised → Guidance pool/default (or applicant choice) → applicant submits sheet → Registrar enrolling → [edge: PH follow-up terminal] → Registrar verifies signed paper + final program → enrolled`

- Registrar acceptance is the exam gate.
- Provisional selection reserves quota; preferences/pool/follow-up do not.
- Open PH follow-up permits `enrolling` but blocks `enrolled`.
- Normal flow needs no PH system approval.
- Signed paper is final-program truth; Registrar may adjust outside pool/quota, capacity stays unchanged, variance is reported.
- This change builds the consultation visualization backbone only. YuKee artifact/K-Means and transparent deterministic/rule-based plugins remain separate later changes under D-093; BGE remains a separate research option.
