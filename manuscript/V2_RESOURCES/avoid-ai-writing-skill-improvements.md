# avoid-ai-writing Skill — Improvement Analysis Based on GPTZero Real Data

> **Source**: GPTZero scan of NEXIAM Background v2 (85% AI probability)
> **Goal**: Add real detector-evidenced patterns to the avoid-ai-writing skill

---

## New Patterns to Add (Ranked by Impact)

### P0 — Add to Word Table (Tier 1 — Always Flag)

These were specifically flagged by GPTZero as having 5-8x higher AI probability:

| Phrase | Flagged Metric | Replace With |
|---|---|---|
| **"gained traction"** | 8.7x more likely AI | "caught on," "seen uptake," "being adopted," "is growing" |
| **"empirical findings"** | 5x more likely AI | "results," "data," "study results," "findings" (alone) |

### P0 — Add "Once X, Y" as a Formulaic Transition

GPTZero flagged "Once results are released, speed matters" as a predictable AI transition pattern.

**Current skill**: Has rules for "Moreover," "Furthermore," "In today's X" but no rule for the "Once X, Y" construction.

**Add to `Transition phrases to remove or rewrite` section**:
- **"Once X, Y" opener** — Sentences starting with "Once" as a temporal connector followed by a main clause. AI uses this as a formulaic transition between paragraphs. Fix: restructure to avoid the "Once" opener. Start with the main clause instead.

*Example:*
- ❌ "Once results are released, speed matters: applicants..."
- ✅ "And when results come out, the speed of delivery directly shapes who enrolls."

### P1 — Add Indirect Speech Reporting Pattern

GPTZero flagged "Liu et al. (2025) showed that conventional admission processes..." as impersonal and formulaic.

**Current skill**: Has rules for "vague attributions" ("Experts believe," "Studies show") but no rule for the repetitive pattern of "[Author] showed/found/demonstrated that [finding]."

**Add to `Structural issues` section**:
- **Uniform citation-reporting structure** — When multiple consecutive sentences follow the identical pattern of "[Author] ([Year]) showed/found/demonstrated that [finding]," the prose reads as AI-generated. Vary the reporting structure: sometimes embed the citation mid-sentence, sometimes lead with the claim and parenthesize the source, sometimes use "According to [Author]," sometimes omit the reporting verb entirely and state the finding directly.

*Pattern to catch:*
- ❌ "Patil et al. (2021) found that..."
- ❌ "Thakur et al. (2025) argued that..."
- ❌ "Liu et al. (2025) showed that..."

*Fix:*
- ✅ "Large-scale exam management creates problems with record integrity (Patil et al., 2021)."
- ✅ "Thakur et al. (2025) made a similar case through their TrueGradeAI framework."
- ✅ "Conventional admission processes still rely on fixed historical yield assumptions (Liu et al., 2025)."

### P1 — Add Textbook Definition Pattern for Technical Concepts

GPTZero flagged the RAG explanation as "extreme concentration of technical jargon" that reads like a generic definition, not a system-specific explanation.

**Current skill**: Has rules for "novelty inflation" and "promotional language" but no specific rule for the "definition dump" pattern.

**Add to `Structural issues` section**:
- **Textbook definition dumps** — When introducing a technical concept (RAG, K-Means, RBAC), AI defaults to writing a generic encyclopedia definition rather than explaining how the specific system uses it. Detect this by checking if the sentence could be copied verbatim into a Wikipedia article without modification. Fix: rewrite the technical explanation in terms of what YOUR system does with the technology, not what the technology is in general.

*Example:*
- ❌ "Retrieval-Augmented Generation constrains output to what institutional policy documents actually say (Lewis et al., 2020), rather than composing responses from a general-purpose model's own parameters."
- ✅ "Retrieval-augmented generation locks the model's responses to approved institutional documents, rather than letting it generate answers from its general training data (Lewis et al., 2020)."

### P1 — Add "This study measures/evaluates/examines" Pattern

GPTZero flagged "This study measures that improvement through the Technology Acceptance Model" as combining technical jargon with impersonal tone.

**Current skill**: No specific rule for this pattern.

**Add to `Structural issues` section**:
- **"This study [verb]s..." impersonal research description** — Sentences that describe what "the study" does using a third-person, impersonal construction. While legitimate in academic writing, AI overuses this as a default. Fix: occasionally shift to active voice with "we" or restructure to focus on the research action.

*Example:*
- ❌ "This study measures that improvement through the Technology Acceptance Model..."
- ✅ "To evaluate this, we apply the Technology Acceptance Model (TAM), looking specifically at how users perceive its usefulness and ease of use."

### P2 — Add "Mechanical Precision" / "Robotic Formality" Check

GPTZero flagged several sentences as having "robotic formality" — technically correct but rhythmically identical subject-verb-object constructions.

**Current skill**: Has rules for "copula avoidance" (using fancy verbs instead of "is") but not the opposite problem of excessively rigid SVO structure.

**Add to `Rhythm and uniformity` section**:
- **Uniform clause structure** — When multiple sentences in a row follow the exact same grammatical pattern (Subject-Verb-Object, no embedded clauses, no fronted adverbials, no inversions), the prose reads as mechanically precise. Flag sections where 3+ consecutive sentences share the same syntactic structure. Fix: vary by fronting an adverbial ("On top of that," "When results come out," "At the same time"), using an occasional compound sentence, or embedding a dependent clause.

---

## Summary: What to Update in the Skill

| Section to Update | Add | Priority |
|---|---|---|
| Word Table (Tier 1) | "gained traction" — 8.7x AI | P0 |
| Word Table (Tier 1) | "empirical findings" — 5x AI | P0 |
| Transition Phrases | "Once X, Y" temporal opener | P0 |
| Structural Issues | Uniform citation-reporting structure | P1 |
| Structural Issues | Textbook definition dumps for technical concepts | P1 |
| Structural Issues | "This study [verb]s..." impersonal research description | P1 |
| Rhythm & Uniformity | Uniform clause structure (mechanical precision) | P2 |
