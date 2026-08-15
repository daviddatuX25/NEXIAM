# NEXIAM — One System for the Whole Admission Journey

**Prepared for:** Guidance Counselor · Test Administrator · Registrar
**Date:** August 2026
**Note to reader:** NEXIAM is being rebuilt from the current system, feature by feature. A few items below are still in development or awaiting your decision, and are marked clearly. Where the system stands today:

- **Exam session scheduling** (both booking modes) — working and demonstrated in the browser.
- **Grading workflow** (grading sessions, per-applicant scoring, finalize, roster eligibility) — working and demonstrated.
- **Bubble-sheet capture** — running in the development environment with a real scanning engine; production activation to confirm.
- **Consultation, release, and enrollment eligibility** — designed end to end; screens are being completed.

---

## 1. What NEXIAM is

NEXIAM is one online system that carries every applicant from application to enrollment. The applicant applies online, takes the exam, sees the result, attends the consultation, and arrives at the Registrar's desk already "cleared" — while the offices behind those steps work from one shared, always-recorded set of records instead of paper files, spreadsheets, and repeated re-typing. Every decision the offices make — who was accepted, who scored what, who was advised, who may enroll — is recorded with who did it and when, so nothing is lost and nothing depends on memory. For the institution, the payoff is simple: faster release cycles, fewer manual checks, an automatic paper trail, and no applicant falling through the cracks. Consultation stays at the heart of the assessment; what changes is the mechanics around it.

---

## 2. The student journey (applicant's view)

The applicant experiences the whole process through a simple portal. Ten steps, end to end:

1. **Apply online** — the applicant submits personal data, grades, and three ordered program preferences, and consents to the privacy policy.
   The system checks that the application window is open and validates every field before accepting the submission.
2. **Receive an access key** — the system issues a private access key, shown once on the confirmation screen and saveable on the applicant's own device; email is the backup.
   From then on, the applicant signs in with the key plus their birthdate — no password to forget, and neither alone is enough to get in.
3. **See their own status** — the portal shows exactly where the application stands, at every stage, from submitted to eligible.
   The applicant sees only their own record — never anyone else's.
4. **Get the exam schedule** — either the office assigns the applicant to a session, or the applicant reserves a seat online, first come first served.
   If self-booking is open, session cards show the date, time, room, and remaining slots with a Reserve button; if the office assigns, the applicant simply sees their schedule.
   Either way, the schedule shown is the single current truth — the system prevents conflicts at the moment of booking.
5. **Take the exam** — attendance and submission are recorded against the session roster.
   A proctor is assigned to that session only, and the roster is live during the exam.
   The office can extend the session deadline when needed; closing the session opens grading exactly once.
6. **Wait for scoring** — answer sheets are captured by bubble-sheet scanning or scored manually; the counselor reviews and finalizes.
   The confidential conversion table turns raw counts into final scores automatically; every result is confirmed by a person.
   A batch cannot be finalized while any applicant's scores are missing, so half-finished work can never be mistaken for finished.
7. **See the result** — released online on the portal, or shown early on a printed sheet at a face-to-face consultation.
   The office prints nothing in online mode; the student can download or print their own copy when needed.
   The student is notified only after the release is truly committed — not before.
8. **Attend the consultation** — the counselor advises the student on the result and the choice of program.
   Until the result is ready, the portal says "Consultation unlocks after your result is ready"; once booking opens, session cards appear with remaining slots.
   Completing the consultation flips the status to "Advised" — a visible milestone on the portal.
9. **Enroll** — the Registrar looks up the name and sees "eligible to enroll" or exactly what is still missing.
   No queueing at other offices, no cross-checking papers — the answer is on screen, and the lookup is recorded.
10. **Done, and on record** — the whole journey stays in the system as a complete, audited history.
    Every action along the way is attributable: who did it, and when.

---

## 3. Features by area

Ten areas, one system. Each row lists what the area does for the institution and the decisions that make it better than the current process.

| Area | What it does for you | Key decisions and why |
|---|---|---|
| **Application and intake** | Applicants apply online with personal data, digital grades, and three ordered program preferences, with privacy-policy consent built in.<br>The Registrar reviews submissions from one queue and can correct, accept, or dismiss each one, with the reason on record. | Digital intake replaces re-keying paper forms, so transcription errors disappear.<br>Corrections are recorded, not silent — a corrected application stays traceable to its original submission.<br>Accept and dismiss are deliberate, logged decisions; a dismissed or corrected applicant can be reopened non-destructively if circumstances change.<br>Hard-copy Form 138/TOR is still required at the Registrar; the system stores the digital grades alongside it for review. |
| **Exam sessions and scheduling** | The office creates exam sessions with date, time, and venue — a room picked from a saved list, with capacity taken from that room.<br>Applicants are then assigned or invited, the schedule is published, and the office runs attendance and submissions from a live roster.<br>Two booking modes, switchable at any time: **office assigns** the seats, or **open reservation** where applicants book their own seat online, first come first served. | Seats come from the room's capacity, so overbooking is impossible. No double-booking can happen — one schedule per student per session, enforced by the system.<br>Publishing controls exactly when applicants can see the schedule; the office can open or close self-booking any time and can still manually assign seats alongside it.<br>Schedules can be published and unpublished, sessions extended or closed. Because the office keeps every toggle, it gains flexibility without losing control.<br>The same scheduling screens and toggle are reused for consultation sessions, so staff who already know exam sessions already know consultation. |
| **Grading and scoring (incl. bubble-sheet capture)** | Grading sessions per exam, per-applicant scoring with review, then finalize — with roster eligibility gated on attendance and submission.<br>Bubble-sheet (OMR) scanning captures answer sheets automatically in the development environment, and the confidential conversion table turns raw counts into final scores automatically. | The confidential conversion table is stored securely and applied by the system, so raw counts become results without manual lookup tables — fewer arithmetic slips at the busiest time.<br>A finalize step cannot be completed while any applicant's scores are missing; every score entry or correction is attributable to the person who made it.<br>Every result is confirmed by a human before it goes out; nothing is released by the system on its own.<br>Manual scoring always remains a working fallback, so a scanner or network outage never stops the office. |
| **Consultation** | Consultation is three separate, required steps instead of one bundled event: **release** (the result becomes visible — face-to-face printed sheets handed out early, or digital on the portal with no office printing), **advising** (the counselor advises; completing it sets the status to "Advised"), and **eligibility** (the Registrar sees eligible — or exactly what is missing).<br>Consultation sessions are scheduled exactly like exam sessions: same screens, room-based capacity, admin-led by default with self-booking available. | No more single-day crowd rush: whichever sessions finish grading and printing first become eligible first, so the workload spreads naturally instead of piling onto one day.<br>Leftovers are caught by later sessions plus a ready-pool list showing who still needs a slot.<br>Nothing is erased — cancellations and changes keep their history, and every action records who did it and when. The counselor's suggestions stay separate from the student's chosen program.<br>Scheduling is blocked until the student's result is ready; self-booking closes when a session is full or when the office disables it; capacity is enforced from the room at every step.<br>An appointment-style option — roughly 50 students per day in hourly slots — was raised in client discussions and can be combined with the open window (to confirm). |
| **Result release** | Scores appear on the applicant portal — no office printing in online mode — or as printed sheets handed out at a face-to-face consultation.<br>Release can be reversed later with a reason, and the history of both the release and the reversal is preserved. | Online release removes the printing queue from the office; face-to-face stays fully supported where the institution wants it.<br>A controlled, human-confirmed release means results never go out by accident or too early.<br>The student is notified only after the release is committed and can retrieve their own copy, cutting counter traffic.<br>A video-call consultation followed by a validated printed copy was also discussed as an online path where face-to-face is not possible (to confirm).<br>(The result-sheet printing pipeline is the next item being completed — timing to confirm with the founder.) |
| **Registrar enrollment eligibility** | The Registrar searches a student's name and sees an eligibility card: exam done, result released, consultation advised — then "eligible to enroll", or the exact missing requirement ("consultation pending", "result not yet released", "follow-up needed"). | Eligibility is computed from four conditions — accepted, exam done, result released, advised — never hand-set, and it re-checks itself if anything changes.<br>The Registrar no longer queues at other offices or cross-checks papers for an answer.<br>The Registrar sees status facts only, never counselor notes or suggestions. Every lookup is recorded. |
| **Applicant portal** | The applicant signs in with an access key plus birthdate and sees only their own record: application status, exam schedule and self-booking, consultation schedule and booking, and released results. | No passwords for applicants to forget; one record per applicant, strictly private.<br>Booking and result retrieval are self-serve, cutting phone calls and counter traffic.<br>The applicant always knows the next step and what is still missing — unreleased results are never visible, and messages are never shown for another applicant's record. |
| **Staff, roles and security** | A clear office hierarchy — support, clerk, office lead, HR — with permission levels enforced by the system, not by habit. Every sensitive action is audited.<br>Program Heads get read-only program summaries and projections, never raw answer sheets or confidential conversion tables. | Each office can only see and do what its role allows, checked at every step, so a clerk cannot silently reach counselor functions and an office cannot touch another office's data.<br>The audit trail answers "who did what and when" for any disputed step.<br>Program Heads get the overview they need for planning without exposing raw or confidential data. |
| **Notifications** | The system informs the right people at the right moments — schedule published, result released, session changed — through an in-app notification center and email where configured. | Notifications fire only after the action has truly been committed, so no one is told something that did not happen, and nothing is announced twice.<br>The notification center never claims that something was sent or released without evidence that it actually happened.<br>(Full delivery channels are still being completed — to confirm with the founder.) |
| **AI assistant (Nexi)** | A built-in assistant available on staff pages that can help draft schedules, suggest wording for records, and answer questions from the institution's own documents. | Nexi proposes — a person confirms. It never writes or changes records on its own, and it cannot act on behalf of a user or skip the checks every staff action passes.<br>AI suggestions are clearly marked as suggestions and are never treated as decisions. |

---

## 4. What changes vs your traditional process

The traditional process is respected, not discarded — but the mechanics change in these concrete ways.

| Today (traditional) | With NEXIAM | Why better |
|---|---|---|
| Release day concentrates everything: printing, release, consultation, and the enrollment crush all at once | Release, consultation, and eligibility are separate steps on their own schedules | No single-day crowd rush; the office absorbs students as sessions finish, not all at once |
| Consultation is bundled into the release day as one event | Three independent, required steps: release, advising, eligibility | Nothing is skipped; every step is verifiable and recorded |
| Results can go out without reliable checks | Every release is controlled and human-confirmed | No accidental or premature results; every release is traceable to a person |
| Double bookings and overbooked rooms happen | Capacity comes from the room; no double-booking is possible | Fewer conflicts, fewer upset walk-ins, less rework |
| The Registrar queues or cross-checks papers to decide if a student can enroll | Searching the name answers it: eligible, or exactly what's missing | Faster counters, consistent answers, no guessing |
| Paper files, re-typing, and spreadsheets | One shared digital record with an audit trail | Less re-keying, fewer transcription errors, disputes settle instantly |
| Leftovers and no-shows can fall through the cracks | Later sessions plus a ready-pool list catch every remaining student | No applicant silently disappears between application and enrollment |
| Manual grading makes results late, pushing release into enrollment | Bubble-sheet scanning plus automatic confidential conversion | Results can be ready days earlier — enrollment can start on time |
| A consultation can be skipped or rushed in the crush of release day | Advising is a required step; the status only becomes "Advised" when it is truly done | The record proves the consultation happened — no shortcuts |
| Office work relies on memory and goodwill about who may do what | Permission levels are enforced by the system on every action, with an audit trail | Accountability without extra supervision |
| Automating risks taking control away from the office | The office keeps every toggle — booking mode, self-booking, publishing — plus manual fallbacks | Automation with human control: the system assists, the office decides |
| Applicants must come back to school for every step — booking, results, updates | Portal self-service for scheduling, booking, and result retrieval | Fewer trips, shorter queues, fewer phone calls |
| Decisions and steps leave no usable history | Complete digital history with who did what and when | Disputes resolve instantly; compliance is provable |
| The office prints and collates every result sheet; institution and student copies are paper-filed | Scores on the portal, a digital record kept by the system, and a student copy the applicant can download or print | Less paper, same rigor — and the record can never be lost or misfiled |
| Staff answer the same questions all day: "Where is my result? When is my schedule?" | The portal shows status at every step and notifications keep applicants informed | Fewer interruptions, calmer counters |
| Exam scheduling and consultation scheduling feel like two different skills | Consultation sessions use the exact same screens and toggle as exam sessions | Staff learn once and use twice — no retraining per process |
| Scheduling is built by hand from scratch every time | The built-in assistant can propose a feasible schedule; the office reviews and confirms | Faster drafting of schedules — the office still makes the final call |
| Exam content and answer keys live only in paper files, read by hand | Bubble-sheet capture reads marks automatically; how much exam content the system holds is the institution's call — from full encrypted storage down to answer keys only | The same confidentiality the institution already requires, with automated reading |

None of these changes ask applicants or staff to relearn the whole process: screens reuse the vocabulary offices already use, and the office keeps the final say on every decision.

A few foundations hold every area above together:

- **One record per applicant** — every step, from application to enrollment, lives on a single record, so no office works from a different version of the truth.
- **Permission levels checked on every action** — staff see and change only what their office role allows, and the check happens every time, not just at login.
- **An audit trail behind every step** — who did what and when is recorded for the whole journey, so disputes resolve from the record, not from memory.
- **A manual fallback for every automated feature** — if a scanner or network hiccups, manual scoring and manual scheduling still work, so the office is never blocked.

---

## 5. Open decisions for your input

These are the choices the client is being asked to confirm. Your answers lock the design.

1. **Release timing** — may results be released before the advising consultation (recommended), or must consultation come first?
   Options: release first (recommended) / consultation first.
   Why it matters: earlier release lets enrollment start on time and spreads the crowd instead of concentrating it.
2. **What counts as "Advised"** — the counselor confirming the decision (recommended), or simply attending the session?
   Options: counselor confirms the decision (recommended) / attendance only.
   Why it matters: "Advised" is the proof that the guidance step truly happened, not just that a seat was filled.
3. **Registrar output** — is the search + eligibility card enough, or does the Registrar also want printed lists?
   Options: search card is enough / printed list as well.
   Why it matters: decides whether eligibility lives on screen or also lands on paper for filing.
4. **Printing** — do students print their own result copy (recommended in online mode), or does the institution print a batch?
   Options: student self-print (recommended) / institution batch print.
   Why it matters: decides how much printing and collating the office keeps doing every cycle.
5. **Big sessions** — may names be declared publicly in the session roster, or should rosters stay office-only?
   Options: public roster (recommended) / names to office only.
   Why it matters: balances openness with student privacy at large, general sessions.
6. **Leftovers** — should the office assign remaining students from the ready pool (recommended), or auto-slot them?
   Options: office assigns (recommended) / auto-slot.
   Why it matters: decides who does the follow-up work for students who did not book on their own.
7. **Self-booking** — start admin-led and add student self-booking later (recommended), or offer both from day one?
   Options: admin-led first (recommended) / both from day one.
   Why it matters: sets how much of the booking workload runs itself from the very first cycle.
8. **Consultation records** — what should the system keep — typed notes (recommended), recordings, or only a summary — who may access them, how long they are retained, and may de-identified records support institutional research such as tracing student success against consultation history? (to confirm with the founder)
   Why it matters: shapes both the privacy and the research value of the counseling record for years to come.

---

## 6. In closing

NEXIAM does not ask the institution to change what it believes in: consultation remains the heart of the assessment, face-to-face stays fully supported, and the counselor and Test Administrator keep every human decision. What changes is the mechanics — one shared digital record instead of paper, scheduling that spreads the load instead of a single crowded day, a Registrar who answers in seconds instead of chasing files, and an audit trail behind every step. The result is less time spent on administration, fewer errors, and confidence that no applicant is lost between application and enrollment.

---

*Prepared from the current NEXIAM project plans (August 2026). Items marked "to confirm" are pending the founder or the client's decision and will be locked once agreed.*
