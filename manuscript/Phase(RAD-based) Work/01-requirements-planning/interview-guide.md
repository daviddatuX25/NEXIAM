# NEXIAM Interview Guide — Round 3 (Final Requirements + Design Squeeze)

**Date:** July 15–16, 2026
**Purpose:** Fill remaining gaps from Rounds 1–2, collect feature justification evidence, and squeeze in design preferences since no separate UI follow-up round is possible.
**Rules:** Few respondents okay. Skip anything already answered. Add-ons noted but don't override scope.

---

## Interview Day Planner

| Role | Who | How Many | Duration | Priority |
|---|---|---|---|---|
| Guidance (Counselor or Admin) | Anyone available | 1 | 25 min | 🔴 HIGH |
| Registrar (Staff or Admin) | Anyone available | 1 | 20 min | 🔴 HIGH |
| Program Heads | Pick across STEM + non-STEM | 2–3 | 12 min each | 🔴 HIGH |
| Applicants (first-year students) | Intercept on campus | 3–5 | 7 min each | 🟡 MEDIUM |

**Total:** ~1.5–2 hours. Order: guidance → registrar → program heads (squeeze applicants between or after).

### What to Bring
- [ ] Printed **cluster-to-program mapping table** (1 row per Program Head — show only their program)
- [ ] Phone camera (photograph marked-up tables, workspaces, answer sheet samples)
- [ ] Blank paper + pen (workflow sketches, participant drawings)
- [ ] This guide — printed or phone

---

## GUIDANCE OFFICE (~25 min, 1 respondent)

### Already Covered — SKIP
Scoring duration/staff/errors (R1 Q4-9), consultation load/FAQs (R1 Q10-12), municipalities/travel (R1 Q16-20), equipment (R1 Q21-23), improvements/concerns (R1 Q24-26), proctoring preferences (R2 Q2-4), scheduling coordination (R2 Q5-8), Yukee system (R2 Q9-11), chatbot openness (R2 Q12-13), quota source (R2 Q14-15).

### Section A: Feature Justification (NEW — ask all)

| # | Question | Why it matters |
|---|---|---|
| G1 | "NEXIAM will scan answer sheets with computer vision instead of manual stencil scoring. How many sheets arrive per batch — all at once or in waves across the cycle? Show us a sample sheet so we can design the bubble grid correctly." **[ARTIFACT: photo of blank answer sheet if available]** | OMR template depends on actual sheet layout and batch volume. |
| G2 | "If the computer flags a bubble as uncertain — smudge, half-erased mark — who should review it: you, or someone else? Should the system auto-score everything else and only flag the uncertain ones?" | Confidence threshold workflow — who verifies, when. |
| G3 | "You mentioned Yukee's system isn't used because it's not integrated into your workflow. NEXIAM will show you TWO panels during counseling: left = aptitude cluster (Math-dominant, Language-dominant, etc.) with matching programs. Right = how well the applicant's stated interests match each program. You see both. You decide placement. Does this approach make sense for how you actually counsel?" | Directly addresses the Round 2 finding — validates the dual-panel Triage Dashboard against their stated concern about integration. |
| G4 | "If aptitude says Math-dominant but interest says BSEd English — what would you do? How do you normally weigh 'what they're good at' vs. 'what they want'?" | Signal weighting — feeds dashboard design and counselor decision logic. |
| G5 | "The RAG chatbot will answer policy questions only — deadlines, requirements, where to submit. No course recommendations. You said it's 'somewhat helpful' — what would make it MORE helpful without crossing into counseling territory?" | Builds on R2 finding. Refines chatbot boundary. |
| G6 | "NEXIAM will log every action — who viewed what, who changed a score, who approved a placement — with cryptographic chaining so tampering is detectable. What kinds of actions worry you most that should absolutely be logged?" | Justifies HMAC-SHA256 audit trail scope. |

### Section B: Design Preferences (NEW — squeeze for User Design)

| # | Question |
|---|---|
| G7 | "When you open an applicant's profile during counseling, what should you see FIRST — their scores, their cluster, their recommended programs, or their application status?" |
| G8 | "Do you prefer more data on one screen or fewer things with bigger text? Is the Guidance Office bright or dim?" |
| G9 | "Draw how you'd arrange the counseling screen if you could design it yourself." **[ARTIFACT: photo of their sketch]** |

### Section C: Add-ons (noted, don't override scope)

| # | Question |
|---|---|
| G10 | "Is there anything NEXIAM could do — within admission testing only — that we haven't discussed? We can't promise it, but we'll catalog it." |

---

## REGISTRAR OFFICE (~20 min, 1 respondent)

### Already Covered — SKIP
No prior Registrar interviews exist. All questions are NEW.

### Section A: Current Workflow (NEW)

| # | Question | Why |
|---|---|---|
| R1 | "Walk us through what happens when Guidance hands you scored results. What do YOU do with them step by step?" **[ARTIFACT: sketch the handoff flow together]** | Baseline workflow — no prior Registrar data exists. |
| R2 | "Where are physical applicant records stored? Who has keys or access? Have records ever gone missing or been accessed by the wrong person?" | Data governance gap — justifies RBAC isolation + audit trail. |
| R3 | "Do you process applications one at a time or in batches? How many per day during peak?" | Informs batch processing UI design. |

### Section B: Feature Justification (NEW)

| # | Question |
|---|---|
| R4 | "NEXIAM separates Guidance views from Registrar views — each office sees only its own data. Right now, can Guidance staff access Registrar records? Should they be able to?" |
| R5 | "When an applicant's status changes — 'results released' — how do applicants find out today? If we automate email notifications, should the email go automatically or should someone review it first?" |
| R6 | "Do you have walk-in applicants come directly to you outside the normal batch? What slows that process down — data entry, verification, something else?" |

### Section C: Design Preferences (NEW — squeeze for User Design)

| # | Question |
|---|---|
| R7 | "When processing an application, what information do you need on the FIRST screen without scrolling?" |
| R8 | "If you had a dashboard showing your office's workload — pending applications, processed results, released scores — what ONE number would matter most?" |

### Section D: Add-ons

| # | Question |
|---|---|
| R9 | "Anything NEXIAM could provide for your office — admission testing only — that we haven't discussed?" |

---

## PROGRAM HEADS (2–3 respondents, ~12 min each)

### Already Covered — SKIP
Quota communication method (PH R1 Q2-3), outdated quota impact (PH R1 Q4-6), current visibility of applicants (PH R1 Q7-8), dashboard feature ratings (PH R1 Q9-11).

### What to Bring
**Printed mapping — ONE row per Program Head showing only their program:**

| Their Program | Matched Cluster | Recommended If |
|---|---|---|
| BSIT | Math-Dominant | Strong Math + Logic percentiles |
| BSCE | Math-Dominant or Spatial-Dominant | Strong Math OR strong Logic/Spatial |
| BSENT | Mixed / High-Across | Balanced across subtests |
| BSEd English | Language-Dominant | Strong Reading Comprehension |
| AB PolSci | Language-Dominant | Strong Reading Comprehension |
| BS Architecture | Spatial-Dominant | Strong Logic + Spatial reasoning |

### Section A: Cluster Mapping Validation (NEW — THE critical task)

| # | Question |
|---|---|
| P1 | "Here's the cluster NEXIAM would map to YOUR program." **[Show their row]** "Based on what you see in students who succeed in your program — does this mapping make sense? Mark directly on the paper: agree, disagree, or suggest a change." **[ARTIFACT: photo of their marked-up mapping]** |
| P2 | "Is there a sub-test score that would make you say 'this student should probably NOT be in my program' — even if their overall score is okay?" |
| P3 | "What matters for success in your program that exam scores can't capture? Creativity? Communication? Something else?" |

### Section B: Design & Feature Justification (NEW)

| # | Question |
|---|---|
| P4 | "During admission season, would you want to see how many applicants are being matched to your program BEFORE the final list comes out? If you had a dashboard showing your program's pipeline — applicants scored, matched, quota remaining — what ONE number would you check first?" |
| P5 | "Should you see individual applicant scores and cluster labels, or just aggregate numbers like '15 matched to BSIT this cycle'?" |
| P6 | "Guidance told us in Round 2 that Sir Sonny coordinates quotas with you. Would a direct dashboard for you reduce those coordination calls, or would you still prefer talking to Guidance directly?" |

### Section C: Add-ons

| # | Question |
|---|---|
| P7 | "Anything NEXIAM could provide for your program during admissions — within the admission testing scope — that we haven't discussed?" |

---

## APPLICANTS / FIRST-YEAR STUDENTS (3–5 respondents, ~7 min each)

### Already Covered — SKIP
No prior Applicant interviews. All questions NEW.

### Context From Prior Rounds (for reference)
- Top applicant questions: "When is exam? Is application still open?" (R1 Q12)
- Origin municipalities: Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, Suyo, Alilem (R1 Q16-20)
- Campus visits for: submitting requirements, taking exam, claiming results (R1 Q18)
- Some travel 2+ hours (R1 Q19-20)

### Questions (keep it quick — 5–7 min)

| # | Question |
|---|---|
| A1 | "How did you find out your exam schedule and results when you applied — Facebook post, text message, came to campus, word of mouth?" |
| A2 | "Did you ever go to campus just to ask a question that you could have checked online — if a website existed?" |
| A3 | "Do you have email and check it regularly? Would getting results by email work for you, or is Facebook/text better?" |
| A4 | "If a chatbot on the website answered questions like 'what's the deadline?' or 'what documents do I need?' — would you use it, or would you rather ask a person?" |
| A5 | "How did you decide what program to apply for — did anyone help you, or did you just pick what you wanted?" |
| A6 | "If you could check application status and results from your phone, what's the ONE thing you'd want to see first?" |
| A7 | "What was the single most frustrating or confusing thing about applying?" |

---

## Post-Interview — Same Day

### Correlation Check
Fill after all sessions:

| Topic | Guidance | Registrar | Prog Head | Applicant | Resolved? |
|---|---|---|---|---|---|
| Handoff: scored → released | | | N/A | | |
| Records access boundaries | | | N/A | N/A | |
| Email: auto or reviewed? | | | N/A | | |
| Cluster → program fit | | N/A | | N/A | |
| Chatbot scope | | N/A | N/A | | |

**Conflict rule:** Applicant need wins. Document resolution.

### Artifacts to File
- [ ] Photos of marked-up mapping tables from Program Heads
- [ ] Photos of any workflow sketches (Guidance handoff, Registrar process)
- [ ] Photos of answer sheet samples
- [ ] Compiled FAQ list (from R1 Q12 + A1–A4)
- [ ] Compiled [DESIGN] notes per role
- [ ] Compiled add-ons / wishlist items
- [ ] Completed correlation matrix

### What This Feeds
- **SO1 (Process Analysis):** Workflow sketches + handoff descriptions
- **SO2 (Requirements):** Feature justification evidence + validated cluster mapping
- **User Design:** Design preference notes (screen priority, layout, data density)
- **Chapter 3 (Results & Discussion):** Cross-role correlation findings, validated gaps
- **Feature Alignment:** Confirmed feature-to-gap traceability

---

## Quick Reference

| Role | Questions | Skipped (from R1/R2) | Duration |
|---|---|---|---|
| Guidance | 10 (G1–G10) | 22 skipped | ~25 min |
| Registrar | 9 (R1–R9) | None (new) | ~20 min |
| Program Head | 7 (P1–P7) | 8 skipped | ~12 min each |
| Applicant | 7 (A1–A7) | None (new) | ~7 min each |

**Total: 33 new questions. ~1.5–2 hours.**

---

*Prior rounds: Round 1 (Guidance questionnaire, 2 resp, 26 Qs), Round 2 (Guidance interview, 1 resp, 15 Qs), Round 1 (Program Head survey, 1 resp, 12 Qs). Files: `Preliminary Interviews & Questionaire/`*
