# NEXIAM — Compact Q&A Defense Cheat Sheet
**Bite-sized. Critical ideas only. Bold = defense pivot.**

---

## SLIDE 2 — Problem: Global & National (David)

**T1 — What's the research gap?**
- Gap is **integration, not invention**
- Every component proven independently: **Dayata & Yap** (IEEE COMNETSAT), **Cuerdo** (PH schools), **Tan** (ACM), **Yukee** (ISPSC ICAT)
- Missing: one **governed RBAC pipeline** Guidance + Registrar can operate during admission season
- **Dash et al.** stopped at design → confirms gap is deployment/governance

**T4 — Lira assessed essays, not admission testing. Relevant?**
- Proves **high-water mark**: AI in admissions validated at **Science Advances**
- Non-speculative — AI assessment is established science
- We apply AI to **operational layer** (scoring, scheduling), not psychometric layer

**T5 — Van Busum & Fang says AI is biased. Why build with AI?**
- **Designed around that finding**, not against it
- 3 safeguards: **RAG→policy docs only**, **K-Means→counselor review**, **human-gated approval**
- Bias finding = **design requirement**, not reason to avoid

**T6 — RA 10173 compliance. How? Why no NPC cert? [CRITICAL]**
- 3 mechanisms: **RBAC data isolation**, **HMAC-SHA256 audit chain**, **RAG→institutional docs only**
- NPC certification + PIA = **outside scope**, future work

**T7 — All local studies from ISPSC. Circular?**
- **Site-specific validation**, not circular
- Yukee: K-Means on **ISPSC's own ICAT data** → works on actual population
- Global = works in general; Local = works **here**

**T8 — Adwan is GCC. Relevant to PH?**
- Relevance is **architectural, not contextual**
- **97% usability** across institutions → integration model is **deployable**

**T9 — Which CHED policy mandates this?**
- **CHED 2022-2028 Strategic Plan** → digital transformation directive
- **RA 10931** → volume pressure + paper process = operational strain

**T10 — Slade & Prinsloo is learning analytics. Why cite?**
- Framework applies **beyond learning analytics**: transparency, data protection, **don't reduce students to profiles**
- All 3 principles **shaped NEXIAM's human-in-the-loop design**

**T22 — Esquivel is enrollment, not admissions. Why?**
- Enrollment predictability → quotas are **forecasts**, not static
- Program Heads need **visibility during cycle**, not after → pre-enrollment slate

**T23 — Ruffalo Noel Levitz is marketing. Why cite?**
- **Practitioner evidence**: processing speed → **applicant yield**
- Academic weight: **French, Sánchez-Mendiola, Liu** | Practitioner: RNL

**T25 — Citation circle? (Yukee, Ballesteros, Angala, Cenezan)**
- **Independent studies, different years/teams** → convergence, not circle
- Proves campus has **research precedent + capability** — lacking **integration**

**T26 — Dayata, Tan — peer-reviewed?**
- Dayata: **IEEE COMNETSAT** → IEEE Xplore indexed
- Tan: **ACM conference** → ACM Digital Library
- Cuerdo: **Peer-reviewed journal**
- Lira: **Science Advances (AAAS)**

---

## SLIDE 3 — Problem at ISPSC Tagudin (David)

**T2 — Why so many features? [CRITICAL — 3-part]**
- **A:** Each feature → documented failure: OMR→scoring delay, Portal→Facebook posting, RBAC→text coordination, Audit→RA 10173. **Remove any = failure persists**
- **B:** Contribution = **integration under one RBAC pipeline**, not individual features. SO4 proves it empirically
- **C:** Scope **explicitly bounded** — enrollment, tuition, records excluded

**T3 — Graduate-level work for undergrads? [CRITICAL]**
- **Borrowing published implementations**, not inventing algorithms
- Cuerdo's OMR, Dayata's RAG, Yukee's K-Means — all documented + replicable
- **AI-augmented RAD**: AI writes boilerplate; team does architecture + integration
- **Human Review gate** — AI writes, human ships

**T11 — How does RAG prevent hallucinations?**
- Standard LLM → training data (stale/wrong). RAG → **search curated KB first**
- Match found → uses as **ground truth**. No match → **"I don't have that information"**
- **Retrieval anchors every response** to institutional documents

**T12 — Why RAG vs fine-tuning?**
- Fine-tuning = **static + expensive** (retrain on every policy change)
- RAG = **update vector DB text** → immediate, zero retraining
- **Real-time accuracy** without continuous training overhead

**T17 — How is K defined? What features?**
- K = **ISPSC program categories**: STEM, Business, Arts, Education
- Features: **sub-test scores** (Math, Logic, Reading Comp, subject sections)
- **Model Adapter** → K-Means swappable without system changes

**T18 — AI making admission decisions? Dangerous? [CRITICAL]**
- **Descriptive, not prescriptive** — does NOT admit/reject
- Counselor makes **final human call** — algorithm proposes, counselor disposes
- 2 safeguards: **manual confirmation required**, **HMAC-SHA256 audit log**

**T24 — Dash stopped at design. Why will you succeed?**
- Dash: concept proven. Us: **methodology advanced**
- Traditional dev (Dash) vs **AI-augmented RAD** (us)
- Every component now has **published implementation** Dash lacked

---

## SLIDE 4 — Objectives (David)

**Objectives justification:**
- **Dependency chain**: diagnose (SO1) → specify (SO2) → build → accept (SO3) → prove better (SO4)
- Each feature **traces to SO1 gap + SO2 requirement** — evidence-driven, not arbitrary

---

## SLIDE 5 — IPO Conceptual Framework (David)

**T13 — 7 roles or 8 actors?**
- **7 RBAC roles** + **Proctor = duty overlay**, not independent role
- Overlay assignable to any staff role → keeps role model clean

**T14 — Where is RBAC in IPO?**
- RBAC = **architectural substrate**, not data input
- Lives in **Process** (authorization matrix defined) + **Output** (every output RBAC-governed)

**T15 — Why IPO not CIPP?**
- IPO aligns with **descriptive-developmental** design
- CIPP overlaps with Ch3's TAM → **duplication**. IPO = focused

**T16 — TAM in Process, not Output?**
- TAM **administration** (measuring) → Process. TAM **results** → Output
- Act of measuring vs what measurement found

**T19 — Counselor blindly follows K-Means?**
- **Audit log** → individual accountability (who approved, when)
- Dashboard shows **multiple clusters w/ confidence** → forces active interpretation

**T20 — Cross-role data isolation?**
- **API-layer RBAC**, not UI cosmetics
- Guidance endpoints **lack Update/Delete on Registrar tables** → structurally prevented

**T21 — HMAC-SHA256: real crypto or logging?**
- **Real cryptographic chaining**: each hash includes **previous event's hash**
- Tamper → chain breaks → **structurally detectable**

---

## SLIDE 6 — Scope & Limitation (Jaypee)

**T28 — 4 months, 3 people, realistic? [CRITICAL]**
- 4 enablers: **published components** (not from scratch), **AI-augmented RAD**, **8-week construction block**, **cross-functional roles**
- **Scope prioritized**: core first, breadth second. Risk-managed schedule

**T29 — Cloud + offline = contradiction? [CRITICAL]**
- Different **operational realities**: offices = connected, exam venues = intermittent
- **Proctoring interface alone** uses service-worker cache → sync on reconnect
- Design response to **documented infrastructure reality**

**T30 — Browser crash during offline exam?**
- SW cache **persists across sessions** → crash doesn't wipe
- Serializes on **every state change** → max loss = one interaction

**T31 — Student hacks offline cache?**
- Cache = **Proctor's authenticated session only** — students never touch proctoring UI
- Sync via **secure token payload** → server validates integrity

**T32 — Free-tier API rate limit?**
- **Graceful degradation**: chatbot → "contact Guidance", scheduler → manual mode
- **Core functions unaffected**: OMR, RBAC, portal, audit — no external API dependency

**T35 — Why cloud, not on-prem?**
- ISPSC: **no dedicated server infra** for app hosting
- Dokploy VPS → production-grade at **fraction of on-prem cost/timeline**

**T38 — Scanner skew misaligns bubbles?**
- **Perspective correction first**: detect 4 anchor corners → warp to flat → then read
- **Cuerdo's validated methodology** — designed for PH scanner variability

**T39 — Rural applicants, no laptops?**
- **Mobile-first, responsive** → smartphone + mobile data = full access
- Addresses **equity gap** directly (vs current = repeat campus trips)

**T40 — SW cache storage limit?**
- **Single-session resilience** (KB, not MB) — not bulk storage
- Intermittent outages (minutes-hours), not prolonged disconnection

---

## SLIDES 8-9 — Methodology & Software Model (Jaypee)

**T41 — Pre/post = quasi-experimental. Why descriptive-developmental? [CRITICAL]**
- **Classified by primary activity**, not one test: describe → develop → evaluate
- Pre/post = **evaluation tool within developmental phase**, not experimental manipulation
- No random assignment, no IV manipulation, no extraneous control → **not an experiment** (Frey 2022)

**T33 — Why RAD, not Agile?**
- **Validated for PH SUC deployments**: Olipas (2020), Melendres & Aranda (2024)
- 4-phase RAD → **clean phase-to-objective mapping** for capstone docs
- Agile iteration complicates documentation structure

**T34 — AI writes code. What did YOU do? [CRITICAL]**
- AI = **routine implementation** (boilerplate, CRUD, UI). Team = 5 things AI cannot:
  - **Architecture** (RBAC schema, Model Adapter)
  - **Specifications** (ISPSC operational reality)
  - **Code review** (human ships, not AI)
  - **Integration layers** (cross-component logic)
  - **Validation** (real staff feedback)

**T37 — 3-person peer review = independent?**
- **Christine = QA only**, no production code → structural separation
- **Dual-role overlap**: every module → 2+ people's eyes

**T27 — 85%/41% AI stats in manuscript? [CRITICAL]**
- From **Osmani (2026)** — cited in Software Model section
- Manuscript = **qualitative justification**; Slide = **quantitative context** (same source)

---

## SLIDE 11 — Population & Locale (Christine)

**T42 — Why no control group? [CRITICAL]**
- **Structurally impossible**: Guidance=2, Registrar=3 → split = n=1-2 (zero power)
- System replaces **entire workflow** — can't run paper + digital on same cohort
- **Within-subjects = correct tool**: each evaluator = own control. Delta answers SO4 directly

**T45 — n=46 underpowered?**
- 30-per-group rule = **independent samples**, not paired designs
- n=46 pairs, medium effect → **power > 0.90**
- Shapiro-Wilk gates normality; effect size reported; Wilcoxon as fallback

**T46 — Purposive sampling = selection bias?**
- **Not convenience**: selected for **operational relevance** (complete office complements)
- Pre-test = **individual baseline** → paired delta accounts for response tendencies

**T36 — September, no real applicants. Valid?**
- Claim: **acceptable to people who would operate it**, not "works in live cycle"
- Evaluators = **same stakeholders** who manage real process
- **First-years = fresh manual-process memory** (months ago)
- Limitation acknowledged: live-applicant validation = future work

**T49 — IT expert excluded from TAM?**
- TAM = **end-user acceptance** constructs — don't fit technical evaluation
- IT expert → **structured technical walkthrough** (architecture, security, privacy, maintainability)
- Qualitative, supplementary. n=1 → no inferential stats

**T55 — First-years already admitted. Invalid?**
- **Fresh institutional memory** of real process → more valuable than hypothetical applicant
- Know exact pain: trips, Facebook, waiting. Can compare genuinely

**T56 — What do 11 Program Heads evaluate?**
- **Pre-enrollment slates** (applicant volume per program + aptitude patterns)
- **Decision-support triage** (program qualification standards, mismatch routing)

**T57 — Hawthorne effect?**
- **Anonymized** TAM (no IDs, no traceability)
- Framed as **critical stress-test** ("find flaws"), not showcase

---

## SLIDE 12 — Research Instrument / TAM (Christine)

**T43 — Manual vs NEXIAM = apples to oranges? [CRITICAL]**
- **That IS the point of SO4** — NEXIAM replaces manual. Only meaningful comparison
- Same **4 constructs, same 7-point scale** — measurement framework identical
- Single post-test = absolute; Paired = **relative improvement**

**T44 — TAM pre-test breaks Davis protocol?**
- Pre-test measures **manual process** (experienced), not NEXIAM (unexperienced) → protocol satisfied
- Paired delta = **shift from manual to automated** — standard IS comparative evaluation

**T47 — 7-point vs 5-point Likert?**
- 7-point → **greater variance**, less midpoint clustering
- Detects **moderate shifts** (expected with manual→digital). **Davis originally used 7-point**

**T48 — 16 items or 64 phrasings?**
- **16 items** (4 constructs × 4). **Role-adapted phrasing only**
- Same construct, contextualized wording. Psychometric properties preserved

**T58 — Why TAM, not SUS/ISO?**
- Primary hurdle = **institutional adoption**, not usability
- TAM → **PU + BIU** predict actual use. SUS = subset. ISO = internal quality (IT expert covers)

**T59 — Ceiling effect? (Pre-test already high)**
- Would be **informative**: problem not perceived as severe = meaningful finding
- Background data (5-14 day delays, Facebook posting) → **low pre-test scores more likely**

**T60 — How measure integration value vs individual features?**
- Evaluators use **complete integrated system**, not isolated modules
- TAM items (PU-4 coordination, PU-3 visibility) → **specifically probe integration value**

---

## SLIDE 13 — Data Analysis (Christine)

**T50 — Shapiro-Wilk low power at n=46?**
- Detects **gross departures**; may miss subtle. 2 safeguards:
  - Report **exact W + p** (not binary normal/not)
  - Report **effect size** (distribution-robust)
- Option: **default to Wilcoxon** (no distribution assumptions)

**T51 — Why effect size + p-value?**
- p = **difference exists?** | d = **how big?**
- n=46 → significant p for trivial shift possible. Cohen's d / rank-biserial → **practical meaning**
- **APA 7th standard**

**T52 — Thematic analysis: how validate themes?**
- **Triangulation**: field observation notes + **stakeholder confirmation**
- Guidance/Registrar verify: "Is this accurate?" → **Only confirmed → requirements**

**T53 — TAM mean > 4.0 = acceptance? Barely above neutral.**
- 4.0 = **scale midpoint** (positive/negative boundary) — TAM literature standard
- Report **exact means + SD per construct per role** transparently
- Paired test (SO4) = complementary: **is it better**, not just acceptable?

**T54 — Why only PU + PEOU hypotheses, not ATU + BIU?**
- PU + PEOU = **core formative constructs** (Davis 1989). ATU + BIU = **downstream mediators**
- PU/PEOU improvement → ATU/BIU **logically follow** (TAM causal chain, decades validated)
- Testing all 4 → **familywise error inflation** (α ≈ .19)

---

## APPENDIX — Weak Points (Name It, Move On)

1. Simulated evaluators → "Results bounded by simulated conditions. Live validation = future work."
2. No NPC certification → "Outside scope. Application-layer RA 10173 alignment only."
3. Free-tier AI → "Paid capacity = production recommendation, outside scope."
4. Osmani whitepaper → "Practitioner evidence. Peng (2023) = peer-reviewed productivity data."
5. n=46 / n=1 → "Sample relatively small. IT expert = qualitative, no inferential stats."
6. September = no cycle → "Acknowledged. Same stakeholders, fresh memory."
7. Woelert duplicate → "Formatting error — will correct."
8. Dash venue quality → "Pivot to Cuerdo, Dayata, Tan — all peer-reviewed indexed venues."

---

## Quick-Reference: Pass-Offs

- **D→C**: "I defer to Christine, QA Tester & Data Analyst, for the statistical methodology."
- **C→J**: "I defer to Jaypee, Lead Developer, for the technical architecture."
- **J→D**: "I defer to David, Project Manager, for the legal and policy framework."
- **→Adviser**: "Reviewed and approved under Dr. Zeus Vincent Mendoza."

---

## Critical-First-5 (Memorize Cold)

1. **"Why so many features?"** → T2 (Slide 3): feature→failure, integration contribution, bounded scope
2. **"This is quasi-experimental"** → T41 (Slide 8): primary activity classification, evaluation tool ≠ experiment
3. **"Why no control group?"** → T42 (Slide 11): staff too few, system replaces workflow, within-subjects correct
