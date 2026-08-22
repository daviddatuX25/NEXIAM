# NEXIAM Client Visit Brief
## Requirements Gathering — Session 2 + User Design — Session 1

**For:** ISPSC Tagudin Campus Guidance Office, Registrar Office, and Program Heads  
**Purpose:** Read through this with the client, confirm what is correct, revise what is not, and record the decisions.  
**Reference visual:** [NEXIAM Canva design](https://canva.link/pxua5lanxtd85mj)  
**Date of validation visit:** ____________________  

---

## 0. Important: how to use this document

This is a **client validation document**, not a claim that every proposed change has already been approved.

- **What we already heard** is labelled as a **documented finding**. It comes from the earlier questionnaires/interviews and must be checked for accuracy by the client.
- **What we want to change** is labelled as a **proposed requirement**. It is a discussion point for this visit.
- **What the client decides today** is recorded in the blank decision and remarks fields.
- If the client says something different, record the correction. Do not silently change the answer to match the system.
- Do not photograph people, student records, answer sheets, messages, or screens without permission. Cover names and identifiers first.

Suggested opening:

> “We will briefly summarize what we understood from our first discussion. Then we will show the process changes we are proposing and the low-fidelity screen sketches. Please correct us if anything is wrong. The system must follow your actual office process—not the other way around.”

---

# Part 1 — Requirements Gathering, Session 1 recap

## 1. What we understood from the first session

This is a compact read-through only. Please correct any item that is incomplete or inaccurate.

| Area | What was documented | Why it matters |
|---|---|---|
| Manual scoring | Scoring one admission cycle takes **more than five days** in the recorded responses. Staff reported problems such as double-marked or unclear answers. | Results can be delayed and manual checking can produce errors. |
| Staff effort | The recorded responses mentioned around **2–3 staff** for one scoring process, with staff trained to help. | The system should reduce repetitive checking, but should keep a human fallback. |
| Consultation volume | One Guidance response described approximately **70–80 applicants per day** during a peak period. The response also described short release/consultation interactions of about **2–5 minutes**. | The office needs a quick way to see the applicant record and record only useful advice. |
| Applicant visits | The response described applicants making about **three campus visits**, mainly for requirements, examination, and claiming results. | Online information and status tracking may reduce unnecessary travel. |
| Long-distance applicants | The responses mentioned applicants travelling from municipalities such as Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, and Alilem. One response confirmed that some applicants may travel for more than two hours. | Clear schedules, status information, and online updates may help applicants plan. |
| Applicant questions | Common questions included: “When is the exam?”, “Is the application still open?”, and questions about requirements, application status, and contact information. | These are candidates for a clear FAQ/chatbot and a visible status page. |
| Proctoring | Guidance staff are considered appropriate exam proctors because they are trained and knowledgeable in administering the ICAT. The response also supported training other institutional personnel under strict procedures if there is a surge. | Additional proctors may be possible, but only with authorization, training, and confidentiality rules. |
| Scheduling coordination | Schedules are coordinated with Guidance staff and other relevant personnel. The main channel mentioned was an official messaging application. Miscommunication happens sometimes. | A shared, trackable schedule can reduce lost or misunderstood messages. |
| Previous clustering output | The earlier clustering output was not integrated into the current workflow. A live dashboard was considered possibly helpful. | Any model output must support—not replace—the counselor’s judgement. |
| Chatbot | A chatbot was considered somewhat useful, especially for admission requirements, exam schedules, application status, contact information, and related routine questions. | Routine questions may be answered faster, with a human fallback when needed. |
| Quota information | Guidance reported obtaining quota information through the person coordinating with Program Heads and relevant personnel. Program Head feedback described face-to-face coordination and daily updates during admission. | A shared quota view and alerts can reduce repeated follow-ups. |
| Program Head dashboard | The Program Head response rated real-time quota capacity, pre-enrollment applicant lists, and threaded inter-office notes as highly useful. | Program Heads can see their own program’s intake situation and communicate a needed action. |

### Session 1 confirmation

The client confirms that the summary above is:

- [ ] Accurate enough to use as the working baseline
- [ ] Accurate after the corrections written below
- [ ] Not accurate; a new description is needed

**Corrections / missing facts:**

______________________________________________________________________________

______________________________________________________________________________

______________________________________________________________________________

---

# Part 2 — Requirements Gathering, Session 2

## 2. What we need to decide today

We are asking the client to validate four things:

1. Which paper or informal activities should become digital.
2. Which current process should change because it is difficult to track, slow, or unclear.
3. Which process must stay the same for confidentiality, training, or office policy.
4. Which screens and information the Guidance Office needs first.

The proposed changes below are written in plain language. They are not final until the client marks them.

---

## 3. Proposed digitized processes: From → To

| No. | Current process (From) | Proposed digital process (To) | Main user | Decision |
|---:|---|---|---|---|
| 1 | Application is written on paper and encoded later. | **Digital application form** saves the applicant’s information once and gives an identifier key. Paper may remain as a backup. | Applicant / Registrar | [ ] Agree [ ] Modify [ ] Keep paper |
| 2 | Applicant identity is connected using a name, NOA number, or paper record. | **One application ID key** is used on the application, admission slip, answer sheet, result, and portal. | Applicant / Registrar / Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 3 | Requirements are checked through paper folders or separate lists. | **Digital requirements checklist** shows missing, submitted, and verified documents. | Registrar | [ ] Agree [ ] Modify [ ] Disagree |
| 4 | Exam slots are coordinated through messages, calls, or face-to-face conversations. | **Shared exam schedule** shows date, time, room, batch size, and proctor. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 5 | Students may wait for the office to assign an exam schedule. | **Optional student booking** may be turned on or off by the authorized office. Staff assignment remains available. | Guidance / Applicant | [ ] Agree [ ] Modify [ ] Disagree |
| 6 | Attendance is written on paper or checked manually. | **Digital attendance** uses the applicant ID/QR on the admission slip. Paper backup remains available. | Proctor / Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 7 | Answer sheets are checked using a stencil or by hand. | **OMR-powered checking** reads the sheet and checks marked answers. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 8 | Unclear, double-marked, or erased answers are judged manually during checking. | **Manual fallback** sends low-confidence answers to a staff review list before the batch is confirmed. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 9 | Raw scores are converted using a separate table or manual calculation. | **Stored conversion table** converts the raw score into the official result after staff confirmation. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 10 | Results are released through a physical process, message, or posted announcement. | **Online result release plus print on request**. A human confirms the batch before publication. | Guidance / Applicant | [ ] Agree [ ] Modify [ ] Disagree |
| 11 | Consultation availability is arranged as applicants arrive or through office coordination. | **Consultation schedule** displays open slots, booked slots, daily capacity, and the consultation mode. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 12 | Consultation is face-to-face only or is arranged informally. | **Two supported modes:** face-to-face and online/video, according to office policy and the applicant’s situation. | Guidance / Applicant | [ ] Agree [ ] Modify [ ] Disagree |
| 13 | Consultation advice is remembered, written separately, or not retained in one place. | **Short typed consultation note** records the advice, date, counselor, and next step. | Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 14 | After consultation, a follow-up may be invisible to the next office or staff member. | **Trackable follow-up** records the action, responsible office/person, target date, remarks, and status. | Guidance / Registrar / Program Head | [ ] Agree [ ] Modify [ ] Disagree |
| 15 | Quota information is requested from a coordinator or Program Head when needed. | **Program quota view** shows current quota information and the last update time. | Guidance / Program Head / Registrar | [ ] Agree [ ] Modify [ ] Disagree |
| 16 | Staff may discover late that a program is near or over its quota. | **Quota threshold alert** notifies the authorized Guidance, Registrar, and/or Program Head staff. | Guidance / Registrar / Program Head | [ ] Agree [ ] Modify [ ] Disagree |
| 17 | Routine questions arrive through messages, calls, social media, or in-person visits. | **FAQ/chatbot** answers approved routine questions and sends unanswered questions to a staff fallback. | Applicant / Guidance | [ ] Agree [ ] Modify [ ] Disagree |
| 18 | Guidance and Registrar coordinate in external messaging channels. | **In-app staff message or task** keeps the request, response, and status connected to the applicant or batch. | Guidance / Registrar | [ ] Agree [ ] Modify [ ] Disagree |
| 19 | Reports are prepared manually from separate lists. | **Role-based reports** show applicants, batches, scores, results, quota, follow-ups, and pre-enrollment information. | Guidance / Registrar / Program Head | [ ] Agree [ ] Modify [ ] Disagree |
| 20 | It is difficult to prove who viewed or changed a record. | **Activity history** records important views, edits, approvals, releases, alerts, and schedule changes. | Authorized staff / IT Admin | [ ] Agree [ ] Modify [ ] Disagree |

### Easy explanation for the Guidance Office

The simple idea is:

> “Instead of asking where the paper, message, score, or follow-up went, the office can open the applicant or batch record and see the current status, the next action, and who handled it.”

The proposed system does **not** remove the Guidance Office’s professional decision. It helps the office record, check, schedule, and follow up more clearly.

---

## 4. Proposed breaking changes: what would be different from today

These are the changes that need the most careful discussion because they change how people work—not just where information is stored.

| No. | Current way | Proposed new way | Why change it? | Decision |
|---:|---|---|---|---|
| B1 | After consultation, the next step may be invisible. | **Trackable handoff:** advice, follow-up, responsible person, due date, and status are recorded. | Prevents applicants from being forgotten and makes responsibility clear. | [ ] Agree [ ] Modify [ ] Keep current |
| B2 | Release and consultation may happen as one crowded activity. | **Separate result release from consultation scheduling.** Results can be released online or printed; consultation has its own slot. | Reduces long lines and lets the office manage daily capacity. | [ ] Agree [ ] Modify [ ] Keep current |
| B3 | Release and consultation may be face-to-face only. | **Support online and face-to-face modes**, depending on the office policy and applicant situation. | Helps applicants who live far away while keeping face-to-face available. | [ ] Agree [ ] Modify [ ] Keep current |
| B4 | The school/admin office controls every exam schedule. | **Office may allow or disable student self-booking** for exam and consultation separately. | Gives the office flexibility: staff-controlled when needed, self-booking when useful. | [ ] Agree [ ] Modify [ ] Keep current |
| B5 | A schedule is coordinated through separate messages. | **One shared schedule** shows rooms, batches, capacity, proctors, and conflicts. | Reduces double booking and unclear updates. | [ ] Agree [ ] Modify [ ] Keep current |
| B6 | Only Guidance personnel may normally proctor. | **Trained non-Guidance staff may be allowed to proctor during a surge**, only if authorized and following confidentiality procedures. | Adds capacity without removing Guidance control over the process. | [ ] Agree [ ] Modify [ ] Keep current |
| B7 | Manual stencil scoring is the main checking method. | **OMR is the first check, manual review is the safety net.** | Speeds up scoring while preserving human checking for unclear marks. | [ ] Agree [ ] Modify [ ] Keep current |
| B8 | A score can move between paper, spreadsheet, and message. | **One confirmed result record** is used for conversion, release, printing, and the applicant portal. | Reduces re-encoding and inconsistent results. | [ ] Agree [ ] Modify [ ] Keep current |
| B9 | Quota information is obtained by asking a coordinator or Program Head. | **Shared quota status with update time and threshold alerts.** | Lets staff act earlier when a program is near capacity. | [ ] Agree [ ] Modify [ ] Keep current |
| B10 | Routine questions require staff time even when the answer is standard. | **Approved FAQ/chatbot for routine questions, with human fallback.** | Reduces repeated questions without allowing the system to invent policy. | [ ] Agree [ ] Modify [ ] Keep current |
| B11 | The clustering/recommendation output is separate from the consultation workflow. | **Counselor dashboard shows model suggestions beside the applicant record.** | Makes the information easier to review, while the counselor still decides. | [ ] Agree [ ] Modify [ ] Keep current |
| B12 | There is no single activity history across offices. | **Important actions are recorded with staff name, date, time, and action.** | Supports accountability and helps resolve disputes. | [ ] Agree [ ] Modify [ ] Keep current |

### Safeguards the team proposes

These safeguards should be confirmed before any change is considered accepted:

- [ ] A human confirms OMR results before release.
- [ ] Manual score entry remains available if scanning fails.
- [ ] The office can disable student self-booking.
- [ ] Online consultation is allowed only under the office’s policy.
- [ ] Confidential exam content is not shown to unauthorized users.
- [ ] A counselor remains responsible for advice and placement decisions.
- [ ] A chatbot can say “I do not have that information” and refer the applicant to staff.
- [ ] Each office sees only the information needed for its work.
- [ ] Paper backup remains available for attendance or outage situations.
- [ ] Changes to important records are recorded in an activity history.

---

## 5. Decision matrix — summary for sign-off

The detailed From → To rows above should be marked one by one. This section records the final direction after discussion.

| Decision area | Final direction | Client remarks / exact wording |
|---|---|---|
| Digital application | [ ] Agree [ ] Modify [ ] Defer | |
| OMR with manual fallback | [ ] Agree [ ] Modify [ ] Defer | |
| Human confirmation before release | [ ] Agree [ ] Modify [ ] Defer | |
| Online result + print on request | [ ] Agree [ ] Modify [ ] Defer | |
| Separate consultation schedule | [ ] Agree [ ] Modify [ ] Defer | |
| Face-to-face consultation remains available | [ ] Agree [ ] Modify [ ] Defer | |
| Online consultation option | [ ] Agree [ ] Modify [ ] Defer | |
| Exam self-booking option | [ ] Agree [ ] Modify [ ] Defer | |
| Consultation self-booking option | [ ] Agree [ ] Modify [ ] Defer | |
| Trackable follow-up and handoff | [ ] Agree [ ] Modify [ ] Defer | |
| Shared quota view and alerts | [ ] Agree [ ] Modify [ ] Defer | |
| FAQ/chatbot with staff fallback | [ ] Agree [ ] Modify [ ] Defer | |
| Staff-to-staff trackable request | [ ] Agree [ ] Modify [ ] Defer | |
| Activity history / audit record | [ ] Agree [ ] Modify [ ] Defer | |
| Counselor remains final decision-maker | [ ] Agree [ ] Modify [ ] Defer | |

### Processes that must not change without further approval

Please identify any process that must remain unchanged:

1. __________________________________________________________________________
2. __________________________________________________________________________
3. __________________________________________________________________________

**Reason / policy basis:**

______________________________________________________________________________

______________________________________________________________________________

---

# Part 3 — User Design, Session 1

## 6. What we will show

The team will show two levels of design:

### Level 1: Low-fidelity wireframe pad

The separate file `ascii-wireframes.html` shows the proposed screen layout using boxes and labels. It is intentionally simple. It is used to ask:

- “Is this the right information?”
- “Is this in the right place?”
- “What is missing?”
- “What should be removed?”
- “What would you click first?”

This is the best item for a photo of the client reviewing the design.

### Level 2: UI kit and detailed HTML wireframes

The file `ui-kit-index.html` opens the existing detailed role wireframes and the locked UI kit reference. This shows the possible visual direction after the workflow is accepted.

**Research integrity note:** These are proposed NEXIAM screens. They are not photographs of the current office process and must not be presented as proof that the current process already looks this way.

---

## 7. User design walkthrough order

### Guidance Office — primary walkthrough

1. **Triage Dashboard** — What information is needed during consultation? Are scores, applicant preference, program information, and quota enough?
2. **OMR Scoring** — Who uploads the scans? Who verifies flagged marks? What does “confirmed” mean?
3. **Exam Scheduling** — Who creates the schedule? When may students self-book? Who approves publication?
4. **Result Release** — Is online release acceptable? When is printing required? Who confirms the batch?
5. **Consultation Notes** — What is the minimum useful note? What follow-up must be tracked? Is a 50-student daily limit realistic?
6. **Proctoring** — What must still work during an internet outage? Who may be trained as a backup proctor?

### Registrar Office walkthrough

1. **Applications list** — What filters and statuses are needed?
2. **Import** — What file is currently used? Which field identifies an applicant?
3. **Processing** — Which requirements and statuses must be visible?
4. **Reports** — Which report is used for coordination with Guidance and Program Heads?
5. **Setup** — Who creates Registrar staff accounts and changes Registrar settings?

### Program Head walkthrough

1. **Quota Pipeline** — What quota, filled, pending, and threshold information is needed?
2. **Pre-enrollment Slate** — Which applicant information is useful and which is confidential?
3. **Program Reports** — What report supports intake planning without making an admission decision?

### Applicant walkthrough

1. **Entry / ID key** — Is the identifier easy to find and use?
2. **Status dashboard** — What is the next step the applicant needs to understand?
3. **Application form** — Which requirements and choices must be included?
4. **Admission slip** — What must be printed or shown on the day of the exam?
5. **Chatbot** — Which questions should be answered automatically, and when should the applicant contact staff?

### System administration walkthrough

1. User management
2. Activity/audit logs
3. Engine registry
4. System settings

The System Administration screens are shown only to discuss governance and settings. They are not a replacement for the Guidance Office’s daily workflow.

---

## 8. User design questions for the client

Please answer with the office’s actual preference, not what sounds technically impressive.

1. What is the **first action** you need to do when an applicant arrives or calls?
   - ________________________________________________________________________
2. What information must be visible without opening another page?
   - ________________________________________________________________________
3. What information must be hidden from other offices?
   - ________________________________________________________________________
4. What is the most dangerous mistake the system could make?
   - ________________________________________________________________________
5. What must still be possible when the internet is unavailable?
   - ________________________________________________________________________
6. What label or word would be easier for your staff than the label shown?
   - ________________________________________________________________________
7. What screen should be tested first in the next review?
   - ________________________________________________________________________

---

## 9. Wireframe validation record

| Role / office | Screens reviewed | Accepted as direction? | Changes requested |
|---|---|---|---|
| Guidance Office | Triage, OMR, Scheduling, Release, Consultation, Proctoring | [ ] Yes [ ] Revise | |
| Registrar Office | Applications, Import, Processing, Reports, Setup | [ ] Yes [ ] Revise | |
| Program Head | Quota, Slate, Reports | [ ] Yes [ ] Revise | |
| Applicant perspective | Entry, Status, Form, Slip, Chatbot | [ ] Yes [ ] Revise | |
| System administration | Users, Audit, Engines, Settings | [ ] Yes [ ] Revise | |

**Most important design change requested:**

______________________________________________________________________________

______________________________________________________________________________

---

# Part 4 — Validation and evidence record

## 10. What we will record after the visit

- Exact corrections to the Session 1 recap
- Each From → To decision: Agree, Modify, Keep Current, or Defer
- New facts or constraints raised by the client
- Screens accepted for the next design review
- Screens or features rejected, with the reason
- Any office policy or confidentiality restriction
- Permission status for photos and documents

## 11. Client validation sign-off

This sign-off means that the client reviewed the summary and proposed direction. It does not mean that every final implementation detail is approved.

**Guidance Office representative**

Name: ____________________________________  Position: ________________________

Signature: _________________________________  Date: __________________________

**Registrar representative**

Name: ____________________________________  Position: ________________________

Signature: _________________________________  Date: __________________________

**Program Head representative**

Name: ____________________________________  Program: ________________________

Signature: _________________________________  Date: __________________________

**NEXIAM team representative**

Name: ____________________________________  Signature: _______________________

Date of transcription: ______________________

### Photo/document permission

- [ ] Permission granted for a photo of the client reviewing the lofi wireframe
- [ ] Permission granted for a photo of marked decision pages
- [ ] Permission granted for anonymized process artifacts
- [ ] No photos permitted
- [ ] Other restriction: ______________________________________________________

---

## Team reminder after leaving the office

Do not write “client approved” unless the client actually marked or stated approval. Keep proposed requirements, confirmed requirements, and unresolved questions separate. The signed/initialed matrix and the dated notes are the evidence for the RAD Requirements Planning and User Design records.

*Prepared for NEXIAM RAD documentation. Source baseline: existing interview/questionnaire files under `01-requirements-planning/Preliminary Interviews & Questionaire/`.*
