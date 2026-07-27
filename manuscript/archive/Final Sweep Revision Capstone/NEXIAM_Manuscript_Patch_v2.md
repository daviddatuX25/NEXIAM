# NEXIAM — Manuscript Find-and-Replace Patch, Version 2
### Supersedes NEXIAM_Terminology_Record_and_Pres_FindReplace.md Part B
### Cross-checked directly against the live Manuscript_Final.pdf, not against drafts
### No em dashes in manuscript or speaker script prose. Slide text may keep them.

---

## STATUS SUMMARY — what changed since v1

v1 was written against the audit findings and your draft revision files.
This version is written against the **actual current manuscript PDF**,
page by page, to catch drift between what was drafted and what was
actually pasted in. Two things had already been fixed since the audit
without being logged (good); two new small things turned up that nobody
had caught yet.

**Already fixed in the live PDF, no action needed:**
- #15 (deployment readiness overclaim, p.25) — already reads "intended to
  measure... once Rapid Construction is complete."
- #16 (RA 10173 "verified" overclaim, p.16) — already reads "supports the
  office's compliance posture... by operationalizing application-layer
  data protection aligned with the Act's key obligations."
- #11 (Proposal Defense "completed") — already reads "which falls within
  June 2026," tense-accurate.
- #14 (guarantee language) — no instance of "guarantee" found anywhere in
  the current manuscript text.
- #18 (duplicate CHED reference) — confirmed removed.

**New patches below, not previously logged:**
- P1: "decision-support tool" survives in two places, inconsistent with
  the locked "decision-support reference" phrase used on p.9.
- P2: the Laravel AI SDK orchestration sentence is still duplicated
  (effectively tripled across the p.13/14 boundary).
- P3: grammar, "a offline-resilient" should be "an offline-resilient."
- P4: Use Case Diagram has no accompanying explanation (Appendix B, p.38).
- P5: Definition of Terms section does not exist anywhere in the
  manuscript. See companion file `NEXIAM_Definition_of_Terms.md`.

---

## PATCH P1 — "decision-support tool" → "decision-support reference"

**Locked position (already correct on p.9, Conceptual Framework):**
> "...functioning as a decision-support reference that informs the
> counselor's judgment of program fit and placement rather than replacing
> it."

**Page 10 (Background, NEXIAM closing paragraph) — currently reads:**
> "...a live K-Means Aptitude Insights Dashboard extending Yukee et al.'s
> (2025) validated clustering configuration into a continuously running,
> every-cycle **decision-support tool**, and a privacy-governed hybrid RAG
> chatbot..."

**Replace with:**
> "...a live K-Means Aptitude Insights Dashboard extending Yukee et al.'s
> (2025) validated clustering configuration into a continuously running,
> every-cycle **decision-support reference**, and a privacy-governed
> hybrid RAG chatbot..."

**Page 14 (Scope and Limitation / SO2 feature block) — currently reads:**
> "...extending Yukee et al.'s (2025) validated clustering configuration
> into a continuously running **decision-support tool**, with Ballesteros
> et al. (2025) providing corroborating evidence..."

**Replace with:**
> "...extending Yukee et al.'s (2025) validated clustering configuration
> into a continuously running **decision-support reference**, with
> Ballesteros et al. (2025) providing corroborating evidence..."

This makes all three instances (pp. 9, 10, 14) read identically, which
closes the cross-cutting pattern the original audit flagged under Finding
#5.

---

## PATCH P2 — Remove duplicate Laravel AI SDK sentence

**Currently, across the p.13/p.14 boundary, this sentence appears three
times in immediate succession** (verified directly in the live PDF text):

> "The chatbot and scheduling assistant are orchestrated through
> Laravel's native AI SDK, which constrains model routing to the SDK's
> supported providers and request patterns rather than a custom inference
> pipeline."

**Action:** Keep exactly one instance. Recommended placement is the first
occurrence in the SO2 system-features discussion (p.14, immediately after
the K-Means Aptitude Insights Dashboard description); delete the second
and third repetitions wherever they fall on p.13 and p.14. This is a
straightforward "find this exact sentence, delete all but the first
match" pass, not a rewrite.

---

## PATCH P3 — Grammar: article agreement

**Page 10, currently reads:**
> "NEXIAM is the proposed response: **a** offline-resilient, role-based
> college admission testing platform..."

**Replace with:**
> "NEXIAM is the proposed response: **an** offline-resilient, role-based
> college admission testing platform..."

---

## PATCH P4 — Use Case Diagram explanation (Appendix B)

The guide requires the Use Case Diagram be accompanied by a brief
explanation of each use case. Appendix B (p.38) is currently the diagram
image only. Insert the following paragraph immediately beneath the
diagram, before the page break into References:

> The use case diagram defines NEXIAM's eight role-based actors,
> Applicant, Proctor, Guidance Counselor, Program Head, Super Admin,
> Registrar Staff, Registrar Admin, and Guidance Admin, against eighteen
> system use cases spanning application intake, exam administration,
> K-Means triage, and audit review. Access is role-isolated by design:
> each actor's permitted use cases are restricted to the functions their
> office actually performs, with Super Admin alone holding account
> creation and role assignment. The diagram should be read alongside the
> RBAC matrix in Chapter 3, which defines the specific permission
> boundaries this figure visualizes.

---

## PATCH P5 — Insert Definition of Terms

Insert the full content of `NEXIAM_Definition_of_Terms.md` as a new
Chapter 1 section, positioned after Significance of the Study and before
the start of Chapter 2. This closes the one remaining required-component
gap from the guide's Chapter 1 structure (Background, Objectives, Scope
and Limitations, Significance, **Definition of Terms** all now present).

---

## CARRIED FORWARD FROM v1 (still required, unchanged)

These were already issued in the prior find-and-replace pass and remain
correct and still pending application, repeated here only so this file is
a complete single source of truth:

- **Slide 5, Layer 03 card text** (Presentation): replace "Descriptive
  aptitude groupings, not a decision the system makes for the counselor"
  with "A decision-support reference that informs the counselor's
  placement judgment, not a system decision." Chip label "K-Means
  Clustering" stays unchanged.
- **Osmani et al. (2026) reference entry**, alphabetical between Olipas
  and Patil: confirmed live link, retained as instructed.
- **Esquivel and Esquivel (2020)**: confirmed restored, no further action.

---

## TERMINOLOGY CONSISTENCY REMINDER

Per the canonical terms table (unchanged from the prior record): any
future edit to manuscript, slides, or script touching K-Means, RBAC,
offline/PWA language, or AI scheduling must use the locked phrasing
exactly as listed there. The two new instances of "decision-support tool"
caught in this pass are the kind of drift that reappears if edits are
made in only one document at a time, so when patching the manuscript,
re-check the speaker script and slide deck in the same pass rather than
treating them as separately scheduled work.
