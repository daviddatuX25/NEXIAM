# Domain 04: Consultation, Counselling & Decision Support

## 1. Domain Responsibility & Academic Value

The Consultation, Counselling & Decision Support domain guides applicants from examination results to degree program qualification. It manages Guidance counseling sessions, career advising interviews, algorithmic course compatibility assessments, departmental quota tracking, and official program endorsement decisions.

In the capstone research, this domain directly resolves baseline problem **FM4** (Long physical consultation queues, subjective course placement, and lack of real-time program quota visibility) documented during Guidance and Program Head interviews.

---

## 2. Core Entities & Database Schema

```
┌───────────────────────────┐         1:N         ┌───────────────────────────┐
│    ConsultationSession    │────────────────────▶│   ConsultationAssignment  │
├───────────────────────────┤                     ├───────────────────────────┤
│ id (BIGINT, PK)           │                     │ id (BIGINT, PK)           │
│ counselor_user_id         │                     │ consultation_session_id   │
│ start_time, end_time      │                     │ admission_attempt_id      │
│ status (ENUM)             │                     │ queue_number (INT)        │
└─────────────┬─────────────┘                     │ status (ENUM)             │
              │ 1:1                               └─────────────┬─────────────┘
              ▼                                                 │ 1:1
┌───────────────────────────┐                                   ▼
│    ConsultationEncounter  │                     ┌───────────────────────────┐
├───────────────────────────┤                     │    ConsultationReceipt    │
│ id (BIGINT, PK)           │                     ├───────────────────────────┤
│ consultation_session_id   │                     │ id (BIGINT, PK)           │
│ format (in_person/online) │                     │ consultation_assignment_id│
│ jitsi_room_jwt (NULLABLE) │                     │ decision (ENUM)           │
│ is_active (BOOLEAN)       │                     │ endorsed_program_id       │
└───────────────────────────┘                     │ notes (TEXT)              │
                                                  │ counselor_signature_hash │
                                                  └───────────────────────────┘
```

1. **`ConsultationSession` & `ConsultationEncounter`:**
   - Represents a counselor's scheduled advising block.
   - Supports both in-person queue management and secure signed Jitsi video encounters (`issueMediaToken`, `allow_empty_token=false`).
2. **`ConsultationAssignment`:**
   - Binds a candidate to a counselor's daily queue with an assigned queue number.
3. **`ConsultationReceipt`:**
   - Append-only, tamper-evident legal record of the advising decision.
   - Records the chosen program, rationale, and counselor digital signature hash.
4. **`ProgramQuota` (`program_quotas` table):**
   - Live tracking of target capacities and remaining available slots across all campus academic programs.

---

## 3. The Unified 5-Card Decision Interface

Guidance Counselors review examinee test profiles and record clinical outcomes using a unified, 5-card atomic decision modal (`RecordConsultationDecision`):

```
┌────────────────────────────────────────────────────────────────────────┐
│                   UNIFIED CONSULTATION DECISION MODAL                  │
├────────────────────────────────────────────────────────────────────────┤
│  [ Card 1: Qualified - First Choice ]                                  │
│  Candidate meets all stanine cutoffs for preferred program.           │
│                                                                        │
│  [ Card 2: Endorsed - Alternate Degree Pool ]                          │
│  First choice full or cutoff unmet; candidate selects viable alternate.│
│                                                                        │
│  [ Card 3: Referred for Academic Interview / Follow-Up ]               │
│  Borderline score; referred to Department Dean for interview review.   │
│                                                                        │
│  [ Card 4: Scheduled for Re-examination / Retake ]                     │
│  Session incident or exceptional retake authorized under campus policy.│
│                                                                        │
│  [ Card 5: Ineligible / Dismissed ]                                    │
│  Scores fail minimum college entrance requirements across all options.│
└────────────────────────────────────────────────────────────────────────┘
```

### Invariants:
- **Payload-bound Idempotency:** Double-clicking cannot double-reserve quota slots.
- **Quota Lock:** Endorsing a program atomically decrements the target quota in a database transaction (`lockForUpdate`).
- **Student Receipt:** The applicant's portal immediately reflects the decision card without exposing internal counselor clinical notes.

---

## 4. Algorithmic Decision Support: TabFM & Honest Hexagon

To prevent subjective or arbitrary placement, NEXIAM integrates the **Admission Support Engine**:

### A. TabFM Course Match Index
- Uses an in-context tabular foundation model (Prior-Labs TabPFN prior) comparing the applicant's profile (SHS strand, GWA, subtest stanines) against **`AdmissionBenchmarkSet`** exemplars derived from historical student success data.
- Emits a normalized **Compatibility Index (0–100%)** categorized into match tiers (*Strong Fit*, *Viable Fit*, *Needs Academic Reinforcement*).

### B. Honest Hexagon Radar Series
- Renders an interactive 6-axis radar visualization comparing the applicant against:
  1. **Applicant Score Profile** (Actual Stanines 1–9)
  2. **Program Minimum Floor** (Approved departmental qualifying threshold)
  3. **Honors Threshold** (Top-tier academic benchmark)
- Prevents cognitive bias by visually grounding the counselor's advising dialogue.

### C. Status of Predecessor Models (YuKee K-Means)
- The legacy clustering model developed by Yukee et al. (2025) is held on **formal standby** awaiting verified `.joblib` model weights under GF-10 Path A. It does not actively generate decisions in the current greenfield build.

---

## 5. Program Head / Academic Dean Coordination

When a candidate is referred for an academic interview (Decision Card 3):
- The candidate appears in the respective Program Head's queue (`/consultation/applicants`).
- The Dean records interview scores and submits a **Program Head Clearance Receipt**.
- The Dean's receipt is appended to the consultation history; it does not overwrite the prior Guidance Counselor record.

---

## 6. Implementation & Verification Status

- **Status:** `[IMPLEMENTED-TESTED]` & `[OBSERVED-EVIDENCE]`
- **Backend Evidence:**
  - `tests/Feature/Release/RecordConsultationDecisionTest.php` (Passing)
  - `tests/Feature/Admission/AdmissionSupportEngineTest.php` (14 tests / 100 assertions passing)
  - `tests/Feature/Admission/TabFmGovernancePreviewTest.php` (Passing)
  - Combined consultation and release suite: 84 tests / 655 assertions passing.
- **Frontend / Browser Evidence:**
  - Authenticated browser checks at 320px, 390px, 768px, and 1440px confirmed sticky mobile tabs, 0px horizontal overflow, and clean radar rendering.
