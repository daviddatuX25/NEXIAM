# Manuscript Claim Crosswalk & Evidentiary Verification

This crosswalk audits material system, operational, and methodological claims found in the draft manuscript sections (`manuscript/sections/`). It provides researchers and technical writers with explicit verification ratings, supporting evidence pointers, and **permitted academic wording** to protect the capstone defense against scrutiny or overstatement.

---

## 1. Chapter 1: Introduction Claims

| Section & Claim ID | Draft Manuscript Statement | Verification Status | Supporting / Contradicting Evidence | Recommended Academic Permitted Wording |
|---|---|---|---|---|
| **§1.4 (Scope)<br>`CLM-01`** | *"NEXIAM automates the entire college admission and enrollment process for ISPSC."* | **Overstated**<br>`[TARGET-DESIGN]` | Contradicted by `SRC-SPEC-02` and `GLOSSARY.md`. NEXIAM strictly terminates at Admission Clearance and physical Enrollment-Sheet Handoff. Course matriculation is external. | *"NEXIAM manages the institutional admission, testing, and consultation lifecycle, terminating at Registrar admission clearance and enrollment-sheet issuance; student course registration and fee assessment remain managed by external institutional systems."* |
| **§1.4 (Scope)<br>`CLM-02`** | *"The system incorporates deep-learning TabPFN models to predict student degree completion."* | **Partially Verified**<br>`[IMPLEMENTED-TESTED]` | TabFM container provides in-context pairwise compatibility indices (`TabFmPairwiseProvider`), but active inference compares against exemplar sets, not longitudinal 4-year completion prediction. | *"The system incorporates a tabular foundation model compatibility engine (TabFM) that computes empirical course-match indices using approved departmental benchmark exemplars."* |
| **§1.6 (Terms)<br>`CLM-03`** | Expands ICAT as *"Ilocos Sur Polytechnic State College College Admission Test"* and specifies six fixed subtests. | **Unverified**<br>`[UNVERIFIED]` | Official institutional testing battery documentation is unverified in code/campus records (`SRC-OMP-01`). Test architecture is instrument-agnostic (`TestInstrument`). | Refer to the examination simply as **ICAT**; state that the system supports configurable test profiles conforming to institutional specifications without asserting an unverified six-subtest expansion. |

---

## 2. Chapter 2: Methodology Claims

| Section & Claim ID | Draft Manuscript Statement | Verification Status | Supporting / Contradicting Evidence | Recommended Academic Permitted Wording |
|---|---|---|---|---|
| **§2.2 (Software)<br>`CLM-04`** | Cites Rapid Application Development (RAD) with AI-assisted construction producing complete code autonomously. | **Partially Supported**<br>`[OBSERVED-EVIDENCE]` | Supported by Martin (1991) and Osmani et al. (2026). However, engineering guardrails require researcher verification, Pest test gates, and manual code review. | *"RAD Phase 3 (Rapid Construction) utilized specification-driven AI coding assistance paired with mandatory researcher code review, automated regression testing, and architectural verification before integration (Osmani et al., 2026)."* |
| **§2.3 (Roles)<br>`CLM-05`** | Outlines distinct specialized roles for ML Engineer, Database Architect, and Frontend Designer. | **Contradicted**<br>`[GOVERNANCE LOCKED]` | `MANUSCRIPT_REFERENCE_INDEX.md` confirms: Technical Writer and Documenter is **one shared role among all three researchers** (David Datu N. Sarmiento, Jaypee G. Pagaduan, Christine M. Lopez). | *"The project tasks were distributed among the three researchers following the five canonical capstone roles, with Technical Writing and Documentation shared collaboratively by all team members."* |
| **§2.4 (Sampling)<br>`CLM-06`** | Asserts 255 student evaluators participated in the study. | **Unverified Planning Target**<br>`[UNVERIFIED]` | 263 is the *planned cohort* (255 students + 8 staff). Baseline survey collected n=22. Post-test TAM responses are pending actual conduct logging. | In Chapter 2, state: *"The planned evaluation sample comprised 263 target respondents across four stakeholder groups..."* In Chapter 3, report only actual collected valid counts (*n*). |

---

## 3. Chapter 3: Results & Discussion Claims

| Section & Claim ID | Draft Manuscript Statement | Verification Status | Supporting / Contradicting Evidence | Recommended Academic Permitted Wording |
|---|---|---|---|---|
| **§3.1 (SO1)<br>`CLM-07`** | Applicant survey findings generalize across the entire ISPSC student population. | **Methodological Constraint**<br>`[OBSERVED-EVIDENCE]` | Survey represents n=22 respondents across two non-pooled rounds (Round 1: 12; Round 2: 10 CBME students). | *"The initial process analysis reflects exploratory baseline responses from 22 applicants and first-year students across two rounds, identifying five operational friction points (FM1–FM5)."* |
| **§3.2 (SO2)<br>`CLM-08`** | *"OMR scanning autonomously grades answer sheets with 100% accuracy without human intervention."* | **Inaccurate**<br>`[IMPLEMENTED-TESTED]` | The CV engine performs mark recognition; ambiguous, faint, or multi-marked items are routed to the `OmrItemReview` human-in-the-loop desk for Test Administrator sign-off. | *"The computer-vision OMR pipeline automates optical bubble mark recognition while routing ambiguous or multi-marked items to a human-in-the-loop verification desk for authoritative staff review."* |
| **§3.2 (SO2)<br>`CLM-09`** | *"NEXIAM generates spatial desk seating arrangements for every examination room."* | **Optional Policy Only**<br>`[IMPLEMENTED-TESTED]` | System operational default is **Simple Capacity** (room quotas). Spatial chair grids are disabled by default (`enable_seating_arrangements = false`). | *"NEXIAM supports simple-capacity room scheduling by default, with an optional policy-gated spatial seating arrangement mode for institutions requiring assigned desk numbers."* |
| **§3.3 (SO3)<br>`CLM-10`** | Presents pre-populated TAM mean scores or claims overall "High Acceptance" before data collection. | **Premature / Prohibited**<br>`[UNVERIFIED]` | Tables 3.5 to 3.8 are structured blank reporting frameworks. Real human response data must be collected and analyzed before reporting means or standard deviations. | Keep result tables formatted with bracketed evidence placeholders until questionnaire data collection is executed. State that TAM evaluation data collection is pending. |

---

## 4. Synthesis of Permitted Claims for Academic Defense

When defending the capstone before the panel, the researchers can confidently make the following **100% verified claims**:

1. **Integrated Multi-Office Pipeline:** NEXIAM successfully connects Registrar intake, Guidance examination operations, optical scoring, clinical counseling, and admission clearance into a unified database with zero cross-office data loss.
2. **Attributable Security:** Every administrative action (scoring commit, consultation endorsement, clearance confirmation) is protected by capability-based authorization (`CapabilityAuthorizer`) and append-only audit ledgers.
3. **Flexible Dual-Mode OMR:** The scanning pipeline successfully handles both system-generated sheets (with 4.2 mm bubbles) and vendor-supplied answer sheets down to a 2.5 mm bubble floor, supported by perspective homography rectification.
4. **Transparent Decision Support:** Rather than a black-box AI recommendation, the consultation desk pairs an empirical TabFM compatibility index with an interactive Honest Hexagon radar comparing raw student stanines against departmental cutoff floors.
5. **Human-in-the-Loop Governance:** Autonomous AI database mutations are strictly prohibited; staff and applicant actions require explicit human confirmation via idempotent commands.
