# NEXIAM Feature Alignment Checklist — Requirements Planning Interview Guide

**Phase:** RAD — Requirements Planning (final validation round before User Design)
**Date:** July 2026
**Purpose:** Before we build, confirm every feature traces to a documented operational gap. This guide is what we bring to the interview with Program Heads, Guidance, and Registrar.

**How to use this document:**
- Column 1: The feature (from Scope & Limitations)
- Column 2: The documented gap it addresses (from manuscript Background / objectives)
- Column 3: Interview questions to ask the participant
- Column 4: Documentation to capture (photo, artifact, diagram)
- Column 5: Confirm / Revise / Remove (filled during/after interview)

---

## FEATURE ALIGNMENT MATRIX

### F1: Computer-Vision OMR Scoring Engine

| Aspect | Detail |
|---|---|
| **Feature** | Batch-scan answer sheets via computer vision. Detect filled bubbles, compare against answer key, flag low-confidence items for human review. |
| **Gap addressed** | Manual scoring takes 5–14 days per cycle. Hand-scoring introduces errors and delays result release. |
| **Scope boundary** | Accuracy validated through TAM acceptance only — NOT benchmarked against exhaustive methods. Bubbles below confidence threshold → human verifies. |
| **Interview questions** | 1. "Walk us through how exam scoring works today — who scores, how, how long?" 2. "On average, how many answer sheets per admission cycle? Per batch?" 3. "How many scoring errors do you recall catching in the last cycle?" 4. "Do answer sheets have multiple-choice bubbles or written answers? Show us a sample." 5. "If the computer flags a bubble as 'uncertain,' who should verify it — Guidance or Registrar?" 6. "What format do scored results need to be in — PDF printout, online portal, both?" |
| **Documentation** | 📸 Photo of a scored answer sheet (blank or anonymized). 📋 Photo of scoring area/workflow setup. 📝 Take photo of the answer sheet layout (bubble grid, identifier number placement). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F2: RAG Chatbot — Policy & Information Q&A

| Aspect | Detail |
|---|---|
| **Feature** | Retrieval-augmented generation chatbot that answers applicant questions from ISPSC institutional documents. Declines to answer below confidence threshold. |
| **Gap addressed** | Staff cannot handle volume of applicant inquiries during peak periods. Applicants currently ask via Facebook page, text message, or in-person visit. |
| **Scope boundary** | Retrieves from institutional policy documents ONLY. Below confidence → says it doesn't know, doesn't fabricate. Validated through TAM, NOT NLP benchmarks. |
| **Interview questions** | 1. "What are the top 5 most common questions applicants ask your office?" 2. "Where are these answers currently documented — handbook, memo, verbal knowledge?" 3. "Are there questions you get every year where the answer changes (deadlines, quotas)?" 4. "If the chatbot says 'I don't have that information,' what should the applicant do next?" 5. "Would you want the chatbot to be available to staff ONLY, or also to applicants?" 6. "What questions should the chatbot NEVER answer — that should go to a human?" |
| **Documentation** | 📋 List of top 10 applicant FAQs from Guidance Office. 📄 Photocopy or photo of any printed admission handbook / policy memo / brochure. 📸 Screenshot of Facebook page inquiries (anonymized). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F3: AI-Assisted Scheduling

| Aspect | Detail |
|---|---|
| **Feature** | AI proposes optimized exam schedules (room, time, proctor assignment). Final schedule requires human approval before activation. |
| **Gap addressed** | Scheduling conflicts require manual resolution. Coordination between Guidance (schedule) and Registrar (rooms/proctors) happens via text message or verbal. |
| **Scope boundary** | Scheduler PROPOSES; authorized officer CONFIRMS. Human-gated. No autonomous assignment. |
| **Interview questions** | 1. "Who is responsible for creating the exam schedule today?" 2. "What conflicts come up — two programs sharing one room, proctor double-booking?" 3. "What constraints exist — room capacity, proctor availability per time slot?" 4. "How far in advance is the schedule published to applicants — 1 day, 1 week?" 5. "If the system proposes a schedule you disagree with, can you modify and re-propose, or just override manually?" 6. "What's the worst scheduling failure you've seen in a past admission cycle?" |
| **Documentation** | 📋 Photo/sample of a past exam schedule (anonymized). 📋 List of rooms usable for exams (venue catalog with capacities). 📋 Current coordination method evidence (screenshot of text thread, memo sample, or verbal description). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F4: Admission Support Models (Cluster Engine + Interest Engine)

| Aspect | Detail |
|---|---|
| **Feature** | Two engines: (1) Yukee Cluster Engine — K-Means aptitude profiling into 4 clusters with program mapping. (2) BGE Interest Engine — semantic similarity matching of applicant preferences to program descriptions. Both displayed separately on Triage Dashboard. Counselor decides. |
| **Gap addressed** | No structured decision support for post-exam counseling. Counselor works from exam scores + intuition. Yukee et al. (2025) validated K-Means on ISPSC data but it sits unused. |
| **Scope boundary** | Descriptive only — surfaces patterns, never makes admission decisions. Every placement requires manual counselor confirmation. Accuracy, tuning, and predictive validity are UNMEASURED. |
| **Interview questions (Guidance Counselor)** | 1. "When you counsel an applicant after the exam, what do you look at — scores, stated preference, something else?" 2. "Do you ever recommend a program the applicant didn't initially want? What makes you do that?" 3. "If the system shows you a cluster saying 'Math-Dominant' but the applicant says 'I want BSEd English,' how would you handle that?" 4. "How much time do you typically spend with one applicant in post-exam counseling?" 5. "What information would you WANT to see on one screen during counseling — scores, cluster, course info, quotas?" |
| **Interview questions (Program Head)** | 1. "For your program (e.g., BSIT), what sub-test scores do you consider a strong fit?" 2. "Is there a sub-test result that would make you say 'this applicant should NOT be in my program'?" 3. "Do you agree with this draft cluster-to-program mapping? If not, which mapping should change?" (Show printed mapping table) 4. "Are there program competencies that aren't captured by exam scores — like creativity, communication — that matter for placement?" 5. "Would you want to see the number of applicants matched to your program during the admission cycle?" |
| **Documentation** | 📋 Printed cluster-to-program mapping table for Program Heads to mark up. 📸 Photo of counselor's actual desk/workspace during consultation. 🎤 Recording or notes of Program Head's verbal justification for mapping changes. |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F5: HMAC-SHA256 Cryptographic Audit Trail

| Aspect | Detail |
|---|---|
| **Feature** | Every system event (data access, placement decision, role action, schedule change) logged with cryptographically chained HMAC-SHA256. Tampering is structurally detectable. |
| **Gap addressed** | Current paper-based process has no audit trail. Disputes ("who changed the score?") rely on memory and physical paper trails. RA 10173 compliance requires data access accountability. |
| **Scope boundary** | Covers ALL system events. NPC certification is outside scope. |
| **Interview questions** | 1. "Have you ever had a dispute about an applicant's score or status? How was it resolved?" 2. "Who currently has access to exam scores after they're recorded — and should everyone have that access?" 3. "If a Registrar staff member accidentally changes an applicant's data, how is that caught today?" 4. "What kinds of actions would you want to see in an audit log — view, edit, delete, approve?" 5. "Should Program Heads be able to view audit logs for their own program's applicants?" |
| **Documentation** | 📋 Describe one real incident (anonymized) where a data dispute occurred. 📋 List of who currently has physical access to score records (file cabinet, spreadsheet). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F6: RBAC-Governed User Roles (7 roles + duty overlay)

| Aspect | Detail |
|---|---|
| **Feature** | Seven role-based access control roles: Applicant, Guidance Counselor, Guidance Admin, Registrar Staff, Registrar Admin, Program Head, System IT Admin. Proctor is a duty overlay, not a distinct role. |
| **Gap addressed** | Currently one person can access everything (paper records in a file cabinet, shared spreadsheet). No role isolation. Cross-office coordination requires physical handoffs. |
| **Scope boundary** | Super Admin exists for oversight/future IT but is NOT evaluated. Account creation is distributed per office (Guidance Admin creates guidance accounts, Registrar Admin creates registrar accounts). |
| **Interview questions** | 1. "What tasks do Guidance and Registrar share — and what should each office NEVER access from the other?" 2. "Who creates accounts for new staff? Should that person be able to see ALL data?" 3. "If the Guidance Counselor is on leave, can someone else temporarily access their dashboard?" 4. "Should Program Heads see only their own program's applicants, or all applicants?" 5. "Do any staff currently share login credentials or use one shared computer? How do we handle that?" |
| **Documentation** | 📋 Draw or describe the current inter-office handoff flow (applicant goes from Guidance → Registrar → etc.). 📸 Photo of physical records storage (file cabinet, desk stack). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F7: Applicant Portal (identifier key, status tracking)

| Aspect | Detail |
|---|---|
| **Feature** | Web portal accessed via unique identifier key (given at registration). Shows exam schedule, results, application status. Mobile-responsive. |
| **Gap addressed** | Currently results posted on Facebook page or bulletin board. Applicants travel to campus to check status. No self-service. |
| **Scope boundary** | Does NOT handle enrollment, tuition, or academic records. Read-only for applicant's own data. |
| **Interview questions (Guidance/Registrar)** | 1. "How do applicants currently check their exam schedule and results?" 2. "How many applicants come to campus in person just to ask 'anong status ko?'" 3. "Do applicants have smartphones? What connection do they use — mobile data or school WiFi?" 4. "What identifier do you currently use to uniquely identify an applicant — name, application number, something else?" 5. "If results are online, do you still want to post printed results on the bulletin board?" |
| **Interview questions (First-year students — optional if accessible)** | 1. "How did you find out your exam schedule and results when you applied?" 2. "Did you have internet access at home during application season?" 3. "Would you trust an online portal over a Facebook post or bulletin board notice?" |
| **Documentation** | 📸 Photo of current bulletin board with posted results (anonymized). 📸 Screenshot of Facebook page result post. 📋 Note typical applicant's mobile data/smartphone situation (observation). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F8: Email Notifications (schedule, results, updates)

| Aspect | Detail |
|---|---|
| **Feature** | Automated email delivery of exam schedules, result notifications, and status updates. Sent from the system, not individual staff. |
| **Gap addressed** | Currently staff manually message or call applicants. No centralized notification system. Applicants rely on word of mouth or Facebook. |
| **Scope boundary** | Uses existing email infrastructure. Free-tier SMTP relay (ElasticEmail via Stalwart). |
| **Interview questions** | 1. "Do applicants typically have email addresses? Do you collect them at registration?" 2. "How do you currently notify applicants of their schedule or results?" 3. "What happens when an applicant says they never received the notification?" 4. "Should email be the PRIMARY notification channel, or a backup to SMS/portal?" |
| **Documentation** | 📋 Sample of current notification — photo of a text message or email template if any exists. |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F9: In-App Messaging (staff-to-staff, applicant-to-staff fallback)

| Aspect | Detail |
|---|---|
| **Feature** | Built-in messaging: (1) inter-office coordination between Guidance and Registrar, (2) applicant fallback if chatbot can't answer. |
| **Gap addressed** | Inter-office coordination currently via text message, Messenger, or verbal — no audit trail. Applicant follow-ups require phone call or in-person visit. |
| **Scope boundary** | Fallback channel — not the primary applicant communication method. Not a full chat platform. |
| **Interview questions** | 1. "How do Guidance and Registrar coordinate right now — text, Messenger, walk to their office?" 2. "Do coordination messages ever get lost or misunderstood? Example?" 3. "If an applicant's chatbot question can't be answered, who should the message route to — Guidance or Registrar?" 4. "Should in-app messages be logged to the audit trail?" |
| **Documentation** | 📸 Screenshot of a coordination text thread (anonymized). 📋 Note typical coordination flow — who contacts whom and how often. |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

### F10: Proctoring Interface (offline resilience)

| Aspect | Detail |
|---|---|
| **Feature** | Service-worker caching provides offline exam session continuity. Proctor marks attendance, monitors session. Syncs to cloud when connection returns. |
| **Gap addressed** | ISPSC Tagudin Campus has intermittent internet connectivity. Exam venues can lose connection mid-session. |
| **Scope boundary** | Proctoring interface ONLY — all other functions require cloud connectivity. Proctor is a duty overlay, not a separate RBAC role. Stores basic session state and attendance markers. |
| **Interview questions** | 1. "How often does the internet go down at Tagudin Campus?" 2. "During exams, do you use paper attendance sheets as backup?" 3. "If the system goes offline mid-exam, what data must still be capturable — attendance, start time, incident log?" 4. "Who typically acts as proctor — Guidance staff, instructors, both?" 5. "When the system reconnects, should it auto-sync all offline data or require manual review first?" |
| **Documentation** | 📸 Photo of current attendance sheet (anonymized). 📋 Note internet stability from staff observation (frequency, duration of outages). |
| **Decision** | ☐ Confirmed — build as scoped ☐ Revised (note change) ☐ Remove |

---

## FEATURE PRIORITY SUMMARY (for build sequencing)

| Priority | Feature | Reason |
|---|---|---|
| P0 — Must ship | OMR Scoring | Core pipeline — without it, system has nothing to score |
| P0 — Must ship | RBAC User Roles | Architectural foundation — everything else depends on role isolation |
| P0 — Must ship | Applicant Portal | Primary applicant touchpoint — score delivery, status, schedule |
| P1 — Ship if time | RAG Chatbot | High visibility, strong defense, but technically separable |
| P1 — Ship if time | Audit Trail | Required for RA 10173, but can be post-MVP layer |
| P1 — Ship if time | Email Notifications | Depends on email infrastructure (already running) |
| P2 — Nice to have | Admission Support Models | Counselor decision support — no dependency on other features |
| P2 — Nice to have | AI Scheduling | Human-gated — manual scheduling works as fallback |
| P2 — Nice to have | In-App Messaging | Redundant if email and chatbot work |
| P2 — Nice to have | Proctoring Interface | Only needed for live exam scenarios (not evaluation phase) |

---

## INTERVIEW FIELD GUIDE (quick reference card)

**Before entering the office:**
- [ ] Printed copies of this checklist (one per interviewer)
- [ ] Printed cluster-to-program mapping table for Program Heads
- [ ] Camera/phone ready for workflow photos
- [ ] Voice recorder ready (ask permission first)
- [ ] Blank paper and pen for flow diagrams and unexpected notes

**During the interview:**
- [ ] Start with "walk us through your current process" — let them talk, then ask feature-specific questions
- [ ] For every gap they describe, ask: "How often does this happen? When was the last time?"
- [ ] Take photos of: workspace layout, physical records, bulletin board, answer sheet sample, current forms/documents
- [ ] Draw a rough workflow diagram together — "draw what happens after an applicant submits their form"
- [ ] Note any feature they mention that ISN'T in our scope — flag for consideration or explicit exclusion

**After each interview:**
- [ ] Mark each feature ☐ Confirmed / ☐ Revised / ☐ Remove
- [ ] Transcribe voice notes within 24 hours
- [ ] File photos with descriptive filenames (e.g., `guidance_scoring_area_2026-07-XX.jpg`)
- [ ] Update the Feature Alignment Matrix above with any new gap discovered

---

## SCOPE BOUNDARY QUESTIONS (to explicitly confirm what's OUT)

These are sensitive — they confirm limitations and prevent scope creep from participant requests.

| Boundary | Question to confirm |
|---|---|
| No enrollment processing | "We're NOT building enrollment — just admission testing. Is that clear and acceptable?" |
| No tuition management | "Payment and tuition tracking is NOT part of this system. Who handles that currently?" |
| No long-term academic records | "Grades after enrollment are NOT in this system. The school's existing SIS handles that, correct?" |
| No NPC certification | "We will NOT apply for formal NPC certification. We'll build to RA 10173 standards at the application layer. Is that acceptable for now?" |
| September evaluation — simulated, not live | "We will test with 271 simulated evaluators, NOT real applicants in a live cycle. Does that affect your willingness to provide honest feedback?" |
| No mobile app — web only | "This is a website, not a phone app. It works on mobile browsers. Is anyone here using a phone that can't browse the web?" |

---

## POST-INTERVIEW: FROM CHECKLIST TO USER DESIGN

Once all interviews are done and features are confirmed/revised:

1. **All confirmed features** → move to User Design phase (generate UI mockups)
2. **Revised features** → update Scope & Limitations manuscript section
3. **Removed features** → document why (missing gap evidence, out of budget, deferred)
4. **New gaps discovered** → add to manuscript Background section as additional justification
5. **Mapping table** → finalize with Program Head validated mappings
6. **Workflow photos** → convert into formal process flow diagrams for Chapter 3

**Output artifact:** A validated Feature Alignment Matrix that Chapter 3 can reference: "Table X shows the mapping of each NEXIAM feature to its documented operational gap, validated through semi-structured interviews with Guidance (n=2), Registrar (n=3), and Program Head (n=11) personnel during July 2026."

---

*This checklist is a living document. Update it after each interview. When all features are confirmed, archive it as requirements-planning evidence for Chapter 3.*
