# NEXIAM Capstone — Client Interview Guide

**Interviewee:** Guidance Office Counselor / Test Administrator
**Interviewer:** NEXIAM capstone team
**How to run:** Speak the **"Say this"** block in your own words (it carries the argument/context), then ask the **"Ask this"** questions. Record answers under each question. Mark anything the client defers or rejects — those become follow-ups.

---

## Part A — Consultation Records & Privacy

### Say this

> "Consultation is the core of the assessment — the result has to be explained to the student properly, otherwise the whole assessment loses value. So to be clear: we are **not** removing consultation. The context here is the drive to make things digital — and the real payoff of that drive is time saved for everyone."
>
> "The second thing worth considering is that a persistent consultation record has value beyond the session itself. It gives the institution a research asset — tracing student success against consultation records over time. For example, studying the effect of strict program requirements in BSIT, or checking whether certain guidance patterns correlate with better outcomes. The records become evidence for decisions, not just notes."
>
> "So the question is how consultation lives as a record, balanced against privacy. There are three options: **(1)** keep a full recording of the session; **(2)** sacrifice the recording and instead have the counselor type the advice directly into the interface — that typed record is what we keep; **(3)** no persistent record at all, consultation stays purely face-to-face."
>
> "If we go with typed notes, I want the note-taking to be fast — it must **not** prolong the consultation. A side feature we're considering is an AI-powered keyword recommendation: as the counselor or Test Administrator types, the system suggests keywords or phrasing, and gets more accurate over time. It speeds up the work of recording, so the record exists without eating into consultation time. It's a convenience layer, not a core feature."

### Ask this

1. Should every consultation session produce a record in the system?
   - Every session / Only sessions with significant advice / No record
   - Answer: _______________________________________________________________

2. If yes, what form?
   - Full audio/video recording / Typed notes / Short summary only
   - Answer: _______________________________________________________________

3. If typed notes: who types them, and when?
   - Counselor during the session / Right after / Later, any day
   - Answer: _______________________________________________________________

4. Who may access a student's record, and how long is it retained?
   - Student self-view? Counselors? Program heads? Admin? / One term / One year / Until graduation / Institutional policy
   - Answer: _______________________________________________________________

5. If a record is corrected later, must we keep the original with an audit trail (who, when, why)?
   - Answer: _______________________________________________________________

6. **Research use:** is it acceptable for consultation records to be used (de-identified) for institutional research — e.g., tracing student success, or program-specific studies like the effect of strict BSIT requirements?
   - Answer: _______________________________________________________________

7. **AI keyword recommendation (side feature):** acceptable to add AI-suggested keywords/phrasing while typing, improving over time — so recording never prolongs the consultation?
   - Answer: _______________________________________________________________
   - If yes: any concern about the model learning from confidential counseling content?
   - Answer: _______________________________________________________________

---

## Part B — Online Result Release & Scheduling

### Say this

> "Release dates currently overlap with enrollment — partly because grading was manual and slow. The OMR system already solves the timing; the remaining question is delivery."
>
> "If results go online — by email or a portal download — students no longer need to line up or come back to school a second time. A release two days before enrollment would even let enrollment run continuously, instead of the current batch-by-batch flow of 100–200 students a day."
>
> "But I want to be clear: consultation stays face-to-face and is **not** removed. It's the heart of the assessment. What I'm proposing is separating it from the big release day and giving it its own schedule."
>
> "My suggestion is **appointment-based consultation** — for example, around 50 students per day in hourly slots, so each student can see and manage their own schedule. And we keep an open batch-consultation window as the default, so we get both modalities."
>
> "And if release genuinely requires face-to-face consultation, there's still an online path: a video-call consultation, after which the student receives a printed hard copy validated by the Test Administrator."

### Ask this

1. How should results be delivered?
   - Online only / Online + print on request / Physical only / Hybrid
   - Answer: _______________________________________________________________

2. If online is acceptable, may release move earlier than enrollment (e.g., two days before)?
   - Answer: _______________________________________________________________

3. Should consultation be separated from the release date into its own period?
   - Answer: _______________________________________________________________

4. Is the appointment model (~50 students/day, hourly slots) acceptable, with an open batch window kept as default?
   - Appointments only / Keep open window / Both
   - Answer: _______________________________________________________________

5. Is online video-call consultation + a validated printed copy acceptable?
   - Answer: _______________________________________________________________

6. Any policy constraints on online release (result confidentiality, identity verification before release)?
   - Answer: _______________________________________________________________

---

## Part C — OMR Scoring, Answer Sheets & Exam Confidentiality

### Say this

> "OMR automates scoring, which means the answer key — and possibly the questions themselves — live in the system. The level of confidentiality the institution requires decides how we handle that."
>
> "Option one, the strongest integrity practice: store the questions in an encrypted, confidential system record, and create a digital answer-sheet template that the OMR reads."
>
> "Option two, if the institution is strict about confidentiality: we adjust — the system stores only item numbers and the answer key, never the question text itself."
>
> "On conversion: the institution uses a conversion table, and we've already set aside the idea of score normalization. I want to suggest publishing the conversion table in the system too, so after scanning, raw counts convert directly into the actual results. That streamlines the whole pipeline: **scan → count → convert → release**."
>
> "And nothing is released by the system on its own — every result is confirmed by a human before it goes out."

### Ask this

1. May full exam questions be stored in an encrypted, confidential record?
   - Yes, full questions (encrypted) / Answer key only / No exam content stored
   - Answer: _______________________________________________________________

2. If full storage is not allowed, is answer-key-only acceptable (items + key, no question content)?
   - Answer: _______________________________________________________________

3. Is a digital answer-sheet template per exam acceptable for the OMR to read?
   - Answer: _______________________________________________________________

4. May the conversion table be stored in the system so raw counts auto-convert to the actual result?
   - Answer: _______________________________________________________________

5. Is release produced directly from the OMR pipeline (scan → count → convert → release) acceptable, with human confirmation before publishing?
   - Answer: _______________________________________________________________

6. Who authorizes which exams/questions get stored, and what happens if confidentiality is at risk?
   - Answer: _______________________________________________________________

---

## Closing — Priorities

1. Of the features discussed — OMR scoring automation, consultation records, online release — which needs the most refinement in the first release?
   - Answer: _______________________________________________________________

2. Which of these must **not** change the current process?
   - Answer: _______________________________________________________________

3. What is the single biggest pain point in today's release/consultation process that the new system must fix first?
   - Answer: _______________________________________________________________
