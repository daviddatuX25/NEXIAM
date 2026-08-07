# D-086 — Intake academic grades snapshot + privacy consent link

**Status:** GO WITH CONDITION — approved by David on 2026-08-05 (**amended same day: catalog-driven grades**)  
**Related:** E2-S02 intake field contract; D-085 applicant access; GF-08 consent

## Decision

### A. Digital academic grades on public apply (consultation evidence)

Hard-copy Form 138 / TOR remains required at the Registrar. Independently, the apply form collects a **digital academic grade snapshot** so Guidance can use it during later consultation without re-keying from paper.

| Item | Binding |
|---|---|
| Purpose | Consultation / counseling evidence only — not enrollment SIS or long-term academic records (BR-026 still holds) |
| Hard copy | Still required F2F at Registrar (Form 138 / TOR) |
| **Data model (scalable)** | Grades are **not** hard-coded subject columns in PHP/UI. Catalog table `intake_grade_fields` (code, label, kind, required, scale, sort, active) drives the apply form; submitted values live in `admission_attempt_grade_values`. Same pattern as `privacy_policies`: seed now, Registrar CRUD later |
| E2 seed defaults | GWA + English + Filipino + Mathematics + Science (active rows — editable via data, not code releases) |
| Disposition | **REWRITE / ADD** — legacy apply had GWA only; founder requires digital grades for consultation |
| **REJECT** | Hard-bounding subject names (`grade_english`, …) in schema/UI/validation |

### B. Privacy / terms consent with full-text link

Legacy apply used: “I have read and agree to the **Privacy Policy**” with a dialog loading the active published policy.

| Item | Binding |
|---|---|
| Disposition | **PRESERVE** legacy consent pattern |
| UI | Checkbox required + hyperlink opens full active privacy/terms text (dialog) |
| Content | Active `privacy_policies` row; seed includes collection of personal data **and** digital academic grades for consultation |
| Staff manage | Registrar setup CRUD may follow later; E2 ships seeded active policy + public read |

## Non-goals (E2)

- Upload of scanned report-card images (separate file intake gate)
- Replacing Registrar hard-copy verification
- Full staff CRUD UI for grade-field catalog / privacy CMS (data model + seed first; CRUD screens follow)
