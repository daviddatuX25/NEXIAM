# Visual Plates Capture & Evidence Catalog (Plates 1–15)

In Chapter 3, Section 3.2 (Specific Objective 2), the manuscript reserves anchors for fifteen visual screenshots (`[PLATE 1]` through `[PLATE 15]`) illustrating NEXIAM's operational interface.

This catalog provides researchers and technical writers with the exact system route, actor, synthetic persona, and capture parameters required to populate each plate cleanly with verified, redacted data.

---

## 1. Global Capture Guidelines

1. **Synthetic Data Only:** Never use real student records or personal phone numbers. Seed deterministic demo fixtures using:
   ```bash
   php artisan demo:stage <scenario> --operator=1
   ```
2. **Standard Viewport:** Capture desktop views at **1440×900 px** or **1920×1080 px**; capture mobile views at **390×844 px** (iPhone 14 standard).
3. **Typography & UI Chrome:** Ensure the locked UI kit is active (IBM Plex Sans, 0.5rem radius, light mode paper + glass background).
4. **Resolution:** Export PNGs at **300 DPI** or double resolution (2x retina) for crisp print reproduction in final DOCX/ODT.

---

## 2. Plate-by-Plate Capture Specification

| Plate Anchor | Interface Description | Target Route & Page Component | Authenticated Actor & Persona | Key Visible Elements & Verification Requirements |
|---|---|---|---|---|
| **`[PLATE 1]`** | **Public Applicant Intake** | `/apply`<br>`Pages/Admission/Apply.svelte` | Public Guest | Clean multi-step intake form; PSGC cascading address selectors (Region $\rightarrow$ Province $\rightarrow$ City $\rightarrow$ Barangay); active RA 10173 privacy policy consent checkbox. |
| **`[PLATE 2]`** | **Applicant Milestone & Schedule View** | `/applicant/portal`<br>`Pages/Applicant/Portal.svelte` | Applicant Persona `PORT-DEFL`<br>(Access Key + Birthdate) | Active milestone timeline (e.g., "Exam Scheduled"); assigned room, timeslot, and printable examination pass with QR code. |
| **`[PLATE 3]`** | **Registrar Intake Review** | `/admissions/{id}`<br>`Pages/Admission/Show.svelte` | Registrar Clerk (`registrar.clerk@nexiam.test`) | Candidate demographic profile; submitted secondary document checklist (Form 138, Good Moral); review action buttons ("Accept", "Request Correction"). |
| **`[PLATE 4]`** | **Examination Session Management** | `/examination`<br>`Pages/Examination/Index.svelte` | Guidance Counselor / Test Admin (`guidance.counselor@nexiam.test`) | Grid of scheduled exam sessions; capacity occupancy gauges (e.g., "30/30 Seats"); floating bulk action dock (Publish, Open Reservation, Cancel). |
| **`[PLATE 5]`** | **Examination Roster & Attendance** | `/exams/monitoring`<br>`Pages/Examination/Show.svelte` | Assigned Proctor (`guidance.clerk@nexiam.test`) | Real-time candidate roster; single-click attendance pills (`present`, `absent`, `void`); room incident reporting dock. |
| **`[PLATE 6]`** | **Scoring Interface (OMR Viewfinder)** | `/assessment/omr/capture`<br>`Pages/Assessment/Omr/Capture.svelte` | Test Administrator | Active camera viewfinder or batch image upload; sequential autocrop preview with detected corner homography pins. |
| **`[PLATE 7]`** | **Mark Review Interface (Human-in-the-Loop)** | `/assessment/omr/review`<br>`Components/Omr/Workbench/OmrReviewStage.svelte` | Test Administrator | Magnified high-resolution optical bubble snippet; model confidence percentage; human override radio buttons. |
| **`[PLATE 8]`** | **Score Completion & Batch State** | `/assessment/grading`<br>`Pages/Assessment/Grading.svelte` | Test Administrator | Consolidated grading roster; subtest raw scores, finalized status badge, and immutable batch commit timestamp. |
| **`[PLATE 9]`** | **Program-Match Review (TabFM Engine)** | `/consultation/applicants/{id}`<br>`Components/admission-support/TabFmResultView.svelte` | Guidance Counselor | Algorithmic Course Match Index (0–100%); match tier badge (*Strong Fit*); interactive 6-axis **Honest Hexagon Radar** series. |
| **`[PLATE 10]`** | **Guidance Consultation (5-Card Decision)** | `/consultation/applicants/{id}`<br>`Pages/Consultation/Dashboard.svelte` | Guidance Counselor | Unified 5-card decision modal displaying Card 1 (Qualified), Card 2 (Alternate Pool), Card 3 (Interview), Card 4 (Retake), Card 5 (Ineligible). |
| **`[PLATE 11]`** | **Result Preparation & Delivery** | `/results/release`<br>`Pages/Release/ResultSheet.svelte` | Guidance Clerk / Support | High-fidelity generated institutional ICAT Result Sheet displaying stanines, raw scores, and official watermark signature. |
| **`[PLATE 12]`** | **Academic Leader View (Dean/Chair)** | `/program-head/dashboard`<br>`Pages/ProgramHead/Index.svelte` | Program Head CAS / CBME (`program.head.cbme@nexiam.test`) | Department quota tracking bar (Enrolled vs Capacity); queue of referred interview candidates; interview note recorder. |
| **`[PLATE 13]`** | **Registrar Handoff & Eligibility View** | `/registrar/eligibility`<br>`Pages/Registrar/EnrollmentEligibility.svelte` | Registrar Admin (`registrar.admin@nexiam.test`) | 4-point eligibility checklist validation; "Confirm Admission Clearance" button; physical enrollment-sheet handoff logger. |
| **`[PLATE 14]`** | **Audit & Event Record View** | `/applicant/access-history`<br>`Pages/Applicant/AdmissionSupport.svelte` | Staff Auditor or Applicant | Append-only, tamper-evident audit ledger displaying event timestamp, acting capability, and redaction flags. |
| **`[PLATE 15]`** | **Office Coordination Thread** | `/consultation/applicants/{id}`<br>`Components/Admission/CaseConversation.svelte` | Guidance Counselor & Department Dean | Bilateral case messaging rail showing internal communication between Guidance and Academic Department regarding an applicant. |

---

## 3. Placement in Final Manuscript

When pasting these captures into `manuscript/sections/03_chapter_3_results_and_discussion/03_2_so2_system_requirements_rad.md`:
1. Replace the bracketed anchor `[PLATE n: ...]` with the formatted image.
2. Ensure the caption immediately follows the image: `**Plate n.** <Title>.`
3. Include a 1-paragraph narrative below the caption describing the specific workflow affordance illustrated in the image.
