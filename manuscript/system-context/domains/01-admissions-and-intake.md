# Domain 01: Admissions & Application Intake

## 1. Domain Responsibility & Academic Value

The Admissions & Application Intake domain governs the entry point for prospective college applicants. It manages digital application submission, Philippine Standard Geographic Code (PSGC) standardized address normalization, academic background intake, statutory privacy consent (RA 10173), and Registrar application review.

In the capstone research, this domain directly addresses process problem **FM2** (Fragmented and delayed applicant information) and **FM5** (Manual paper trail and lack of persistent tracking) identified in the SO1 baseline.

---

## 2. Core Entities & Database Schema

```
┌─────────────────────────┐          1:N         ┌─────────────────────────┐
│    ApplicantIdentity    │─────────────────────▶│     AdmissionAttempt    │
├─────────────────────────┤                      ├─────────────────────────┤
│ id (BIGINT, PK)         │                      │ id (BIGINT, PK)         │
│ access_key (VARCHAR, UQ)│                      │ applicant_identity_id   │
│ birthdate (DATE)        │                      │ academic_cycle_id       │
│ first_name, last_name   │                      │ reference_code (VARCHAR)│
│ sex, civil_status       │                      │ status (ENUM)           │
│ email, contact_number   │                      │ preferred_program_id    │
└─────────────────────────┘                      └────────────┬────────────┘
                                                              │ 1:N
                                                              ▼
                                                 ┌─────────────────────────┐
                                                 │  CaseConversationThread │
                                                 ├─────────────────────────┤
                                                 │ id (BIGINT, PK)         │
                                                 │ admission_attempt_id    │
                                                 │ audience (ENUM: public/ │
                                                 │           internal)     │
                                                 └─────────────────────────┘
```

1. **`ApplicantIdentity` (`applicant_identities` table):**
   - Represents the permanent human applicant across potential multiple cycles.
   - Authorizes portal access via `access_key` (uppercase alphanumeric token) + `birthdate` (ISO date). *Decision D-085: No free-form passwords for applicants.*
2. **`AdmissionAttempt` (`admission_attempts` table):**
   - Represents a specific application submission for a given academic cycle (`AcademicCycle`).
   - Tracks the admission state machine (`draft` $\rightarrow$ `submitted` $\rightarrow$ `under_review` $\rightarrow$ `accepted` $\rightarrow$ `scheduled` $\rightarrow$ `examined` $\rightarrow$ `consulted` $\rightarrow$ `ready_for_clearance` $\rightarrow$ `cleared` $\rightarrow$ `dismissed`).
3. **`CaseConversation` / `AdmissionSupportMessage`:**
   - Isolated communication threads enabling bilateral messaging between the applicant and campus offices (Registrar, Guidance).

---

## 3. Human Actor Journeys & Interaction Flow

### A. Public Applicant Journey (`/apply`)
1. **Intake Form:** Applicant visits `/apply`, selects their target campus/program, inputs demographic data, and inputs Senior High School (SHS) strand and General Weighted Average (GWA).
2. **Address Selection:** Enforces hierarchical PSGC selection (Region $\rightarrow$ Province $\rightarrow$ Municipality/City $\rightarrow$ Barangay) with zero manual typos.
3. **Privacy Consent:** Displays full active institutional Privacy Policy modal; requires explicit affirmation checkbox.
4. **Credential Issuance:** Upon submission, atomically returns a secure 12-character `access_key` displayed once with local print/save recommendations.

### B. Applicant Portal Authentication (`/applicant/login`)
- Authenticates using **Access Key + Birthdate (MM-DD-YYYY)**.
- Grants access to `/applicant/portal`: tracks current application status milestone, examination schedule slip, digital result sheet, and bilateral messaging rail (`/applicant/admission-support`).

### C. Registrar Admissions Desk (`/admissions`)
- Authorized staff (`registrar.applications.view`, `registrar.applications.manage`) review incoming submissions.
- **Actions Available:**
  - `AcceptAdmissionAttempt`: Confirms submitted documents; marks candidate eligible for ICAT examination scheduling.
  - `RequestApplicationCorrection`: Flags unreadable documents or invalid data with a mandatory note sent to applicant's portal.
  - `DismissAdmissionAttempt`: Atomically rejects ineligible applications with required administrative justification.

---

## 4. Key Actions & CQRS Queries (`system/app/Admission/`)

| Class | Type | Responsibility & Invariants |
|---|---|---|
| `SubmitAdmissionAttempt` | Domain Action | Validates intake payload, creates identity & attempt records, generates reference code and access key. |
| `AcceptAdmissionAttempt` | Domain Action | Transitions attempt to `accepted`; publishes event notifying Guidance exam planners. |
| `DismissAdmissionAttempt` | Domain Action | Requires explicit `reason_code` and notes; invalidates pending exam reservations. |
| `RequestApplicationCorrection`| Domain Action | Transitions status to `correction_requested`; opens bilateral messaging thread. |
| `SendAdmissionSupportMessage` | Domain Action | Appends message to case thread; enforces audience boundary (`internal_staff` vs `applicant_visible`). |
| `ShowAdmissionAttemptQuery` | CQRS Query | Returns projected attempt details, audit log, and requirement checklist. |
| `CaseConversationQuery` | CQRS Query | URL-isolated token projection preventing cross-attempt metadata leakage. |

---

## 5. Security Invariants & Business Rules

- **BR-ADM-01 (Credential Isolation):** Applicant portal authentication never accepts raw passwords. The `access_key` is generated using CSPRNG bytes and hashed at rest.
- **BR-ADM-02 (Audience Fencing):** Internal staff notes recorded on an application attempt are tagged `audience = internal_staff` and are strictly excluded from applicant API responses.
- **BR-ADM-03 (Immutability after Acceptance):** An accepted application cannot be edited by the applicant; corrections require staff authorization (`AuthorizeAcceptedAdmissionAmendment`).

---

## 6. Implementation & Verification Status

- **Status:** `[IMPLEMENTED-TESTED]` & `[OBSERVED-EVIDENCE]`
- **Backend Evidence:**
  - `tests/Feature/Admission/AdmissionFlowTest.php` (Passing)
  - `tests/Feature/Admission/ApplicantAccessTest.php` (Passing)
  - `tests/Feature/Admission/ApplicantSubmissionGuidanceTest.php` (19 tests / 244 assertions passing)
  - `tests/Feature/Admission/ApplicantOfficeInquiryTest.php` (Passing)
- **Frontend / Browser Evidence:**
  - Production bundle compiled cleanly (Vite Svelte 5 runes).
  - Responsive layout verified at 390px mobile viewport (zero horizontal overflow) and 1440px desktop.
