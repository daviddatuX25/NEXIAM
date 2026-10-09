# NEXIAM — Lofi ASCII Wireframes (all screens, detailed UX)

**RAD User Design — Session 1 (simulated)** · Print via `ascii-wireframes.html`
Each screen shows: top bar (who is logged in), navigation, and the main areas with the actions that matter. These are **lofi** — they show arrangement and workflow, not final styling. The real look is in the UI kit (`ui-kit-reference/`).

---

## PART A — APPLICANT PORTAL (5 screens)

### A1. Login / Entry

```
+----------------------------------------------------------------------+
|  [NEXIAM logo]   ISPSC Tagudin — Admission Portal        [Help]       |
+----------------------------------------------------------------------+
|                                                                      |
|                     Welcome to NEXIAM                                |
|                     Admission Testing Portal                         |
|                                                                      |
|        +--------------------------------------------------+          |
|        |  Enter your ID Key                                |          |
|        |  [ APP-2026-XXXX_______________ ]                 |          |
|        |                                                    |          |
|        |  [  Continue  ]      [ I forgot my key ]           |          |
|        +--------------------------------------------------+          |
|                                                                      |
|      Your ID Key was given when you submitted your application.     |
|      It is printed on your admission slip.                           |
|                                                                      |
|   Privacy note: only you can see your own records with this key.     |
+----------------------------------------------------------------------+
```

### A2. Dashboard (status tracker)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo]   Applicant: Juan Dela Cruz      ID: APP-2026-0451    |
+----------------------------------------------------------------------+
|  Home          |   APPLICATION STATUS                               |
|  My Application|                                                      |
|  Admission Slip|  1. Submitted   2. Exam slot   3. Exam taken        |
|  Chatbot       |  [DONE]    ==>  [SCHEDULED]  ==>  [PENDING]         |
|  Results       |     4. Results       5. Consultation                 |
|                |     [WAITING]          [NOT YET]                     |
|                |                                                      |
|                |   NEXT STEP:                                         |
|                |   Your exam is on July 18, 2026, 8:00 AM, Room 204. |
|                |   [ View admission slip ]                            |
|                |                                                      |
|                |   +-- Did you know? ------------------------------+ |
|                |   | Ask the chatbot about requirements or your     | |
|                |   | schedule — it answers 24/7.                    | |
|                |   +------------------------------------------------+ |
+----------------------------------------------------------------------+
```

### A3. Application Form

```
+----------------------------------------------------------------------+
|  [NEXIAM logo]   Applicant: Juan Dela Cruz      ID: APP-2026-0451    |
+----------------------------------------------------------------------+
|  Home          |   MY APPLICATION                                    |
|  My Application|                                                      |
|  Admission Slip|  1. Personal Information                             |
|  Chatbot       |     Name      [ Juan Dela Cruz            ]         |
|  Results       |     Birthdate [ 2006-03-14 ]  Sex [M]              |
|                |     Address   [ Sta. Cruz, Ilocos Sur    ]         |
|                |                                                      |
|                |  2. Program Choice                                   |
|                |     First choice  [ BS Information Technology  v ]  |
|                |     Second choice [ BS Computer Engineering    v ]  |
|                |                                                      |
|                |  3. Requirements Checklist                           |
|                |     [x] Report card      [ ] Brgy. clearance        |
|                |     [x] PSA birth cert  [ ] 2x2 ID photo            |
|                |                                                      |
|                |  4. [ Submit application ]    (saves as draft)       |
|                |     Drafts are saved automatically as you type.     |
+----------------------------------------------------------------------+
```

### A4. Admission Slip (printable)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo]           ADMISSION SLIP                 [Print]      |
+----------------------------------------------------------------------+
|                                                                      |
|   +--------------------------------------------------------------+  |
|   |   ISPSC TAGUDIN CAMPUS — COLLEGE ADMISSION TEST (ICAT)        |  |
|   |                                                               |  |
|   |   Applicant: Juan Dela Cruz         ID: APP-2026-0451         |  |
|   |   Program:   BS Information Technology                        |  |
|   |   Date:      July 18, 2026     Time: 8:00 AM                  |  |
|   |   Room:      204              Seat: A-12                      |  |
|   |                                                               |  |
|   |   [ QR / ID KEY ]        Bring: pencil, eraser, this slip.    |  |
|   |                                                               |  |
|   +--------------------------------------------------------------+  |
|                                                                      |
|   Things to remember:                                                |
|   - Arrive 30 minutes before the exam                                |
|   - Phones are not allowed inside the room                            |
|   - Results are released through this portal                          |
+----------------------------------------------------------------------+
```

### A5. Chatbot

```
+----------------------------------------------------------------------+
|  [NEXIAM logo]   Applicant: Juan Dela Cruz      ID: APP-2026-0451    |
+----------------------------------------------------------------------+
|  Home          |   NEXIAM ASSISTANT (chatbot)                        |
|  My Application|                                                      |
|  Admission Slip|  +----------------------------------------------+  |
|  Chatbot       |  | Nexi: Hi Juan! Ask me about requirements,     |  |
|  Results       |  |       schedules, or your application status.  |  |
|                |  +----------------------------------------------+  |
|                |  +----------------------------------------------+  |
|                |  | You: Kelan po ang exam?                       |  |
|                |  +----------------------------------------------+  |
|                |  +----------------------------------------------+  |
|                |  | Nexi: Your exam is July 18, 2026, 8:00 AM,   |  |
|                |  |       Room 204. Want me to show your slip?   |  |
|                |  +----------------------------------------------+  |
|                |                                                      |
|                |  Suggested: [Requirements] [Exam schedule]          |
|                |             [Application status] [Talk to staff]   |
|                |  [ type your question here .............. ] [Send]  |
|                |                                                      |
|                |  Not sure → "Talk to staff" sends your question     |
|                |  to the office with a tracking number.               |
+----------------------------------------------------------------------+
```

---

## PART B — GUIDANCE OFFICE (6 screens)

### B1. Triage Dashboard (admission support)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · Triage Dashboard   [Guidance Cslr]|
+----------------------------------------------------------------------+
|  Triage Dashboard|  APPLICANT: Juan Dela Cruz   ID: APP-2026-0451   |
|  OMR Scoring     |                                                      |
|  Exam Scheduling |  +-- Aptitude Profile (Yukee Cluster v1) --------+ |
|  Result Release  |  | [scatter plot]   Cluster: Math-Dominant       | |
|  Consultation    |  |                 Confidence: 0.89             | |
|  Proctoring      |  | Recommended: BSIT · BSCE · BS Math           | |
|                  |  +----------------------------------------------+ |
|                  |  +-- Interest Match (BGE engine) --------------+ |
|                  |  | 1. BSIT (0.92)  2. BSCE (0.84)  3. BSA (0.61)| |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Exam score: 87/100    HS GPA: 91                 |
|                  |  [ Open consultation notes ]  [ Recommend... ]    |
|                  |                                                      |
|                  |  NOTE: engines only suggest. Counselor decides.   |
+----------------------------------------------------------------------+
```

### B2. OMR Scoring

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · OMR Scoring        [Guidance Cslr]|
+----------------------------------------------------------------------+
|  Triage Dashboard|  BATCH: ICAT June 2026 — Session 4 (45 sheets)     |
|  OMR Scoring     |                                                      |
|  Exam Scheduling |  1. [ Upload scans ]    (camera or scanner)        |
|  Result Release  |  2. [ Run OMR check ]                              |
|  Consultation    |                                                      |
|  Proctoring      |  PROGRESS: [#########--------] 23/45  (1.5 min)    |
|                  |                                                      |
|                  |  FLAGGED FOR HUMAN VERIFICATION (2):              |
|                  |  +--- Score: 78/100 · No. questioned: 2 ---------+ |
|                  |  | #12 double-marked        [View] [Accept]      | |
|                  |  | #34 erased + remark      [View] [Accept]      | |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Manual fallback: [ Enter scores by hand ]        |
|                  |  [ Confirm batch ]  → converts to results + audit |
+----------------------------------------------------------------------+
```

### B3. Exam Scheduling

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · Exam Scheduling    [Guidance Cslr]|
+----------------------------------------------------------------------+
|  Triage Dashboard|  SCHEDULE WEEK: July 14–18, 2026                  |
|  OMR Scoring     |                                                      |
|  Exam Scheduling |  +-- Proposed batch (auto) ----------------------+ |
|  Result Release  |  | July 18 · 8:00 AM · Room 204 · Proctor: A.    | |
|  Consultation    |  | 40 applicants · counselor on duty: M. Santos  | |
|  Proctoring      |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Constraints shown: room capacity 50 · proctor     |
|                  |  availability · 30-applicant default batch         |
|                  |                                                      |
|                  |  Actions: [Edit] [Re-propose] [ Confirm & publish ]|
|                  |  Student booking option: [ ] allow self-booking    |
|                  |                                                      |
|                  |  PUBLISHED → applicants see their slot in portal.  |
+----------------------------------------------------------------------+
```

### B4. Result Release

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · Result Release      [Guidance Cslr]|
+----------------------------------------------------------------------+
|  Triage Dashboard|  BATCH: ICAT June 2026 — Session 4 (45 sheets)     |
|  OMR Scoring     |                                                      |
|  Exam Scheduling |  Conversion table: raw → percentile (stored)        |
|  Result Release  |  e.g. 78/100 → 88th percentile                     |
|  Consultation    |                                                      |
|  Proctoring      |  +-- Release list (auto-converted) ---------------+ |
|                  |  | APP-2026-0451  Juan Dela Cruz   88%  OK      | |
|                  |  | APP-2026-0452  Maria Garcia    92%  OK      | |
|                  |  | APP-2026-0453  [flagged → verify]      ----  | |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  [ Preview result sheet ]  [ Print result sheets ]|
|                  |  [ Release online ]   → portal + email            |
|                  |                                                      |
|                  |  ⚠ Nothing publishes automatically — a counselor  |
|                  |  confirms before release.                          |
+----------------------------------------------------------------------+
```

### B5. Consultation Notes

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · Consultation        [Guidance Cslr]|
+----------------------------------------------------------------------+
|  Triage Dashboard|  TODAY'S SLOTS: 50 max · 24 booked · hour slots    |
|  OMR Scoring     |                                                      |
|  Exam Scheduling |  [9:00] Juan Dela Cruz   [9:00] Maria Garcia       |
|  Result Release  |  [10:00] — open —        [11:00] Ben Cruz          |
|  Consultation    |                                                      |
|  Proctoring      |  MODE: [F2F] [Online video] — per session           |
|                  |                                                      |
|                  |  Notes for: Juan Dela Cruz  (typing — auto-saved)   |
|                  |  +----------------------------------------------+  |
|                  |  | Recommended BSIT. Strong math profile.        |  |
|                  |  | Follow up: bring PSA copy to registrar.       |  |
|                  |  +----------------------------------------------+  |
|                  |                                                      |
|                  |  Follow-up: [ open ] [ date: Jul 25 ]  Quota check: |
|                  |  BSIT 120/150 slots — [x] advise now               |
|                  |  [ Save & complete ] (record kept, audit logged)    |
+----------------------------------------------------------------------+
```

### B6. Proctoring (offline-resilient)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Guidance Office · Proctoring          [Proctor role] |
+----------------------------------------------------------------------+
|  Triage Dashboard|  SESSION: ICAT July 18 · Room 204 · 8:00 AM        |
|  OMR Scoring     |  Status: [● OFFLINE — local mode] (will sync)      |
|  Exam Scheduling |                                                      |
|  Result Release  |  +-- Attendance (scan QR on slip) ---------------+ |
|  Consultation    |  | [Scan] → APP-2026-0451 Juan Dela Cruz  ✓ 9:02| |
|  Proctoring      |  | [Scan] → APP-2026-0452 Maria Garcia   ✓ 8:58| |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Present: 40/40    Absent: 0                     |
|                  |                                                      |
|                  |  Incident log:                                    |
|                  |  [ + Add incident ]  e.g. late, phone seen,      |
|                  |  materials — timestamped, kept even offline      |
|                  |                                                      |
|                  |  [ End session ] → syncs when connection returns. |
+----------------------------------------------------------------------+
```

---

## PART C — REGISTRAR OFFICE (5 screens)

### C1. Applications (list)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Registrar Office · Applications      [Registrar Staff]|
+----------------------------------------------------------------------+
|  Applications   |  FILTERS: [Search name/ID____] Status [All v]       |
|  Import         |  Program [All v]   Batch [All v]                    |
|  Processing     |                                                      |
|  Reports        |  +-- Applicants (1,240 this cycle) ---------------+ |
|  Setup          |  | APP-2026-0451 Juan Dela Cruz   BSIT   Complete | |
|                  |  | APP-2026-0452 Maria Garcia    BSCE   In review | |
|                  |  | APP-2026-0453 Ben Cruz        BSED   Quota hit | |
|                  |  +----------------------------------------------+ |
|                  |  [ Export CSV ]  · records are registrar-scoped    |
+----------------------------------------------------------------------+
```

### C2. Import

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Registrar Office · Import             [Registrar Staff]|
+----------------------------------------------------------------------+
|  Applications   |  IMPORT APPLICANTS FROM FILE                        |
|  Import         |  [ Choose CSV / Excel file ]                       |
|  Processing     |  [ Download template ]                              |
|  Reports        |                                                      |
|  Setup          |  Preview matched columns:                            |
|                  |  File column [Name] → system [full_name]     ✓      |
|                  |  File column [Course] → system [program]     ✓      |
|                  |  File column [NOA #] → system [id_key]       ⚠ dup  |
|                  |                                                      |
|                  |  Errors found: 2 (1 duplicate NOA, 1 empty name)    |
|                  |  [ Fix in file ]  [ Import valid rows only ]        |
|                  |                                                      |
|                  |  Every import is logged to the audit trail.        |
+----------------------------------------------------------------------+
```

### C3. Processing (per applicant)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Registrar Office · Processing        [Registrar Staff]|
+----------------------------------------------------------------------+
|  Applications   |  APPLICANT: Maria Garcia   ID: APP-2026-0452        |
|  Import         |                                                      |
|  Processing     |  Requirements:                                       |
|  Reports        |  [x] Report card      [x] PSA birth cert            |
|  Setup          |  [ ] Brgy. clearance  [ ] 2x2 photo                 |
|                  |                                                      |
|                  |  Status: [ IN REVIEW ]  →  [ Verified ]             |
|                  |                                                      |
|                  |  ⚠ Program BSCE has reached 100% of quota.         |
|                  |  [ Alert guidance office ]  (sends in-app alert)    |
|                  |                                                      |
|                  |  History: created Jun 30 by R. Mallari · verified   |
|                  |  Jul 02 by A. Ramos (audit logged)                  |
+----------------------------------------------------------------------+
```

### C4. Reports

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Registrar Office · Reports            [Registrar Admin]|
+----------------------------------------------------------------------+
|  Applications   |  REPORT TYPE: [Pre-enrollment list v]               |
|  Import         |  PERIOD: [June 2026 – July 2026 v]   [ Generate ]   |
|  Processing     |                                                      |
|  Reports        |  Summary:                                             |
|  Setup          |  Total applicants: 1,240  ·  Verified: 1,180        |
|                  |  Per program: BSIT 150 · BSCE 120 · BSED 90 ...       |
|                  |                                                      |
|                  |  +-- Table: applicants per program, status -------+ |
|                  |  | Program | Applied | Verified | Quota | %       | |
|                  |  | BSIT    | 168     | 160      | 150   | 107%   | |
|                  |  | BSCE    | 122     | 118      | 120   | 98%    | |
|                  |  +----------------------------------------------+ |
|                  |  [ Export PDF ]  [ Export CSV ]                    |
+----------------------------------------------------------------------+
```

### C5. Setup (accounts + settings)

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Registrar Office · Setup              [Registrar Admin]|
+----------------------------------------------------------------------+
|  Applications   |  REGISTRAR STAFF ACCOUNTS                           |
|  Import         |  Name                Email               Role       |
|  Processing     |  R. Mallari          r.mallari@...       Staff      |
|  Reports        |  A. Ramos            a.ramos@...          Staff      |
|  Setup          |  [ + Create account ]    (Registrar-scoped only)    |
|                  |                                                      |
|                  |  SETTINGS (registrar-scope):                        |
|                  |  Default program quotas  [edit]                    |
|                  |  Identity key format    [ APP-2026-XXXX ]          |
|                  |                                                      |
|                  |  Guidance cannot see this screen, and vice versa.  |
+----------------------------------------------------------------------+
```

---

## PART D — PROGRAM HEADS (3 screens)

### D1. Quota Pipeline

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Program Head · BSIT Quota              [Program Head]|
+----------------------------------------------------------------------+
|  Quota Pipeline |  BS INFORMATION TECHNOLOGY                          |
|  Pre-enroll Slate|  Quota 150  ·  Applied 168  ·  Filled 107%         |
|  Program Reports |                                                      |
|                  |  [#########------] 118 slotted · 42 pending        |
|                  |                                                      |
|                  |  ALERTS:                                            |
|                  |  ⚠ Quota reached (100%) on Jul 02 — registrar was   |
|                  |    alerted automatically, guidance was notified.   |
|                  |                                                      |
|                  |  [ Update quota ]  [ Set alert threshold (95%) ]    |
|                  |                                                      |
|                  |  You see only your own program's data.             |
+----------------------------------------------------------------------+
```

### D2. Pre-Enrollment Slate

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Program Head · BSIT Pre-Enrollment     [Program Head]|
+----------------------------------------------------------------------+
|  Quota Pipeline |  SLATE — BSIT (sorted by exam rank)                 |
|  Pre-enroll Slate|  +-- Recommended applicants ---------------------+ |
|  Program Reports |  | Rank | Name            | Score | Cluster      | |
|                  |  | 1    | Juan Dela Cruz  | 88%   | Math-Dom     | |
|                  |  | 2    | Maria Garcia    | 92%   | Math-Dom     | |
|                  |  | 3    | Ben Cruz        | 85%   | Spatial      | |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  [ Add comment ] per applicant (visible to Guidance)|
|                  |  [ Request review ]  → guidance coordinator        |
|                  |                                                      |
|                  |  Slate is advisory — admission decisions stay       |
|                  |  with Guidance/Registrar.                           |
+----------------------------------------------------------------------+
```

### D3. Program Reports

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | Program Head · Reports                 [Program Head]|
+----------------------------------------------------------------------+
|  Quota Pipeline |  BSIT INTAKE SUMMARY — 2026 CYCLES                  |
|  Pre-enroll Slate|  [June intake v]  [ Chart: applied vs quota ]       |
|  Program Reports |                                                      |
|                  |  Applied: 168  Verified: 160  Quota: 150            |
|                  |  Top municipalities: Sta. Cruz, Bangar, Sudipen    |
|                  |  Avg exam score of slotted: 81/100                 |
|                  |                                                      |
|                  |  [ Export PDF ]  [ Export CSV ]                    |
|                  |                                                      |
|                  |  Charts are descriptive — for planning only.       |
+----------------------------------------------------------------------+
```

---

## PART E — SYSTEM IT ADMIN (4 screens)

### E1. User Management

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | System Admin · User Management          [IT Admin]   |
+----------------------------------------------------------------------+
|  User Mgmt     |  FILTER: Office [All v]  Role [All v]               |
|  Audit Logs    |  +-- Accounts -------------------------------------+ |
|  Engine Registry|  | Name        | Office     | Role        | Status | |
|  Settings      |  | Sir Sonny   | Guidance   | Admin       | Active | |
|                  |  | M. Santos  | Guidance   | Counselor   | Active | |
|                  |  | R. Mallari | Registrar  | Staff       | Active | |
|                  |  +----------------------------------------------+ |
|                  |  [ + Create account ]  [ Deactivate ]  [ Reset pw ]|
|                  |                                                      |
|                  |  Role assignment is per office — no cross-office   |
|                  |  account creation.                                 |
+----------------------------------------------------------------------+
```

### E2. Audit Logs

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | System Admin · Audit Logs               [IT Admin]   |
+----------------------------------------------------------------------+
|  User Mgmt     |  VERIFY CHAIN: [ Check integrity ]  → OK (HMAC)     |
|  Audit Logs    |                                                      |
|  Engine Registry|  +-- Events (filters: date, actor, action) --------+|
|  Settings      |  | 2026-07-02 09:14  A. Ramos   verified APP-...452 | |
|                  |  | 2026-07-02 09:15  System    alert: BSCE quota 100%| |
|                  |  | 2026-07-02 11:02  M. Santos  released batch 4   | |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Every view, edit, release, and schedule change is |
|                  |  recorded. Tampering is detectable.                |
+----------------------------------------------------------------------+
```

### E3. Engine Registry

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | System Admin · Engine Registry          [IT Admin]   |
+----------------------------------------------------------------------+
|  User Mgmt     |  Engines registered in NEXIAM:                      |
|  Audit Logs    |  +-- Admission support -----------------------------+|
|  Engine Registry|  | Yukee Cluster v1   [● running]  [config] [logs] | |
|  Settings      |  | BGE Interest v1    [● running]  [config] [logs] | |
|                  |  +----------------------------------------------+ |
|                  |  +-- Scoring ------------------------------------+ |
|                  |  | OMR CV Engine       [● ready]  [threshold 0.85]| |
|                  |  +----------------------------------------------+ |
|                  |  +-- Assistant ----------------------------------+ |
|                  |  | Nexi RAG (policy)   [● running]  [doc sources] | |
|                  |  +----------------------------------------------+ |
|                  |                                                      |
|                  |  Engines only surface suggestions; humans decide.  |
+----------------------------------------------------------------------+
```

### E4. Settings

```
+----------------------------------------------------------------------+
|  [NEXIAM logo] | System Admin · Settings                 [IT Admin]   |
+----------------------------------------------------------------------+
|  User Mgmt     |  SYSTEM SETTINGS                                     |
|  Audit Logs    |  OMR confidence threshold  [ 0.85  ]                |
|  Engine Registry|  Default batch size         [ 30   ]                |
|  Settings      |  Result release mode        [ human-confirmed v ]   |
|                  |  Student self-booking       [ off  ]  (toggle)     |
|                  |  Consultation max/day       [ 50   ]               |
|                  |                                                      |
|                  |  Notification channel       [ email + portal v ]   |
|                  |                                                      |
|                  |  [ Save ]  → every change is audit-logged.         |
+----------------------------------------------------------------------+
```

---

*End of wireframe pad — 23 screens (5 applicant + 6 guidance + 5 registrar + 3 program head + 4 admin).*