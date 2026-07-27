# NEXIAM Proposal Defense — Q&A Defense Bank (v1)

**Team Drill Target:** ~55 traps across 3 domains  
**Use:** Each person reads their traps aloud × 3, pair drill, then full run-through with panelist player  
**Convention:** `[CRITICAL]` = guaranteed to be asked; `[HIGH]` = likely from engaged panelist; `[MEDIUM]` = sharp panelist territory; `[ELITE]` = rare but devastating

---

## DOCUMENT ALIGNMENT: Issues Found During Audit

Fix these BEFORE drill practice. A panelist holding the manuscript against your script will catch them.

### Script Fix Needed
- **Slide 10 script: "proxy evaluators"** — Change to **"purposively selected evaluators"** or **"simulated evaluators."** The manuscript never uses "proxy" — it says "simulated evaluators" and "purposive sampling." Using "proxy" implies experimental substitution, which contradicts your descriptive-developmental design.

### Script Risk Point (No Change Needed, But Be Ready)
- **Slide 8: 85%/41% AI stats** — Jaypee currently reads these percentages aloud. These numbers are from the Osmani (2026) Kaggle whitepaper. The manuscript cites Osmani but does NOT state these specific percentages in the body. Panelist question: "Where in your manuscript do these numbers appear?" **Answer:** "The statistics are from Osmani's 2026 Google whitepaper on agentic engineering, which we cite in our Software Model section. We display them on the slide as a visual anchor, but you are correct — they are not quoted verbatim in the manuscript body. The manuscript instead describes the paradigm shift qualitatively, citing both Osmani and Peng (2023) on productivity gains."

### Manuscript Fix Needed (Inform the Adviser)
- **Woelert (2023) duplicate in reference list** — The reference list contains two identical entries for Woelert (2023). Remove one.

---

## SECTION 1: DAVID — Background, Architecture, Legal Framework

---

### TRAP 1: The "So What's the Gap?" Question [CRITICAL]

**Q:** "What exactly is the research gap you're filling? Aren't there already admission systems?"

**David:**
> The gap is not invention — it is integration. Every component NEXIAM needs has been proven independently. Dayata and Yap deployed a working RAG chatbot at USC, published at IEEE COMNETSAT. Cuerdo validated low-cost OMR in Philippine public schools. Tan proved K-Means for course routing at an ACM conference. Yukee et al. ran K-Means on ISPSC's own ICAT data. These pieces all exist and are published.
>
> What does not exist is one governed pipeline that runs them together — scoring, aptitude assessment, classification, and structured guidance — inside a role-based architecture that the Guidance Office and Registrar can actually operate during an admission season. That pipeline is the gap. Dash et al. (2026) proposed a similar unified platform at the design stage and stopped there, confirming the gap is in deployment and governance, not in concept.

---

### TRAP 2: The "Why So Many Features?" Question [CRITICAL]

**Q:** "You have OMR scoring, K-Means, RAG chatbot, AI scheduling, RBAC, audit trails — isn't this scope too broad for a three-person team?"

**David:**
> Each feature maps to a documented operational failure at ISPSC — we did not choose features for novelty. Hand-scoring takes five days to two weeks per cycle — that maps to OMR. Applicants monitor a Facebook page during office hours — that maps to the portal. Inter-office coordination runs on unstructured text messages — that maps to RBAC with shared dashboards. Remove any feature and a documented failure persists.
>
> The contribution is the integration, not the individual components. Each piece is independently proven. Our novel element is making them run together as one governed pipeline — and proving the integrated whole outperforms the manual method.
>
> The scope is bounded. Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are all explicitly excluded. The manuscript scope section names what is outside.

---

### TRAP 3: The "Aren't These Features Too Advanced for an Undergrad Capstone?" Question [CRITICAL]

**Q:** "OMR with computer vision, RAG chatbots, K-Means clustering — isn't this graduate-level work?"

**David:**
> Every component is borrowed from existing, published implementations — we are not inventing new algorithms. The OMR pipeline builds on Cuerdo's validated approach for Philippine public schools. The RAG chatbot replicates Dayata and Yap's architecture, which is documented in their IEEE paper. The K-Means module uses Yukee's already-validated model trained on ISPSC's own ICAT data. We are integrating proven building blocks, not conducting original ML research.
>
> The AI-augmented RAD methodology further accelerates construction. Osmani (2026) documents that 85% of professional developers now use AI coding agents regularly. We apply the same paradigm: AI generates the routine implementation work, our team focuses on architecture and integration. This is how a compact academic team can deliver an enterprise-grade pipeline in a four-month window.

---

### TRAP 4: The "Lira Doesn't Prove Your Case" Question [HIGH]

**Q:** "You cite Lira et al. (2023) from Science Advances to support AI in admissions, but their study assessed personal qualities through essays — not the kind of admission testing you're doing. How does that apply?"

**David:**
> Lira proves the high-water mark: AI-based applicant assessment is validated at the highest level of peer review — Science Advances. Their finding that ML models can extract predictive signal from unstructured application materials establishes that AI-driven admission is not speculative. It works, and it works at scale.
>
> We are not replicating Lira's methodology. We are building on the foundation they laid by applying AI to the operational infrastructure layer — scoring, scheduling, applicant classification — rather than the psychometric layer. Lira opens the door for AI in admissions. NEXIAM walks through that door on the operational side.

---

### TRAP 5: The "Van Busum and Fang Contradicts You" Question [HIGH]

**Q:** "You cite Van Busum and Fang (2025) on AI bias in admissions. If AI models can be biased, why are you building a system with AI components?"

**David:**
> Precisely because bias is real — which is why we designed NEXIAM around that finding, not against it. Three safeguards: First, the RAG chatbot is restricted to institutional policy documents — if no document matches above a confidence threshold, the system says it does not have the information rather than fabricating. Second, K-Means surfaces aptitude patterns for counselor review — it does not make admission decisions. The algorithm proposes; the counselor disposes. Third, every AI-assisted output in NEXIAM — exam schedules, triage notes — passes through a human-gated approval step before taking effect.
>
> Van Busum and Fang's finding is not a reason to avoid AI. It is the design requirement that shaped our architecture. The system proposes; the authorized officer disposes.

---

### TRAP 6: The "RA 10173 Compliance — Prove It" Question [CRITICAL]

**Q:** "You claim NEXIAM aligns with the Data Privacy Act. How, specifically? And why no NPC certification?"

**David:**
> Three specific mechanisms: RBAC-enforced data isolation ensures each role accesses only the data its office is authorized to see — a Registrar cannot view Guidance counseling notes, an Applicant cannot see another applicant's scores. HMAC-SHA256 audit trails make every data access event tamper-evident — any unauthorized modification is structurally detectable. And the RAG chatbot draws only from institutional policy documents, not unrestricted language models, which prevents external data leakage.
>
> Formal NPC certification and the Privacy Impact Assessment are identified as outside scope. We designed to RA 10173's requirements at the application layer, and we state this limitation explicitly. Full regulatory certification requires a process separate from system development — it is future work that we acknowledge.

---

### TRAP 7: The "Why ISPSC Specifically?" Question [MEDIUM]

**Q:** "Your local studies — Yukee, Ballesteros, Eustaquio — are all from ISPSC. Isn't that circular? You're using your own campus's studies to justify a system for your campus."

**David:**
> That is exactly the point, panelist. A system built for ISPSC must be grounded in ISPSC's operational reality. Yukee et al. validated K-Means clustering on ISPSC's own ICAT entrance exam data — meaning we know the algorithm works on our actual applicant population, not just on a generic dataset. Eustaquio and Nisperos demonstrated that a single digital platform can support multiple administrative functions on this campus. Angala and Cenezan proved digitization projects succeed here.
>
> The local studies are not circular reasoning — they are site-specific validation. The global and national literature establishes that the approach works in general. The local studies establish that it will work here, with our data, our workflows, and our constraints.

---

### TRAP 8: The "What About CHED Policies?" Question [MEDIUM]

**Q:** "You mention CHED's strategic plan. What specific CHED policy mandates a system like NEXIAM?"

**David:**
> CHED's 2022-2028 Strategic Plan explicitly directs SUCs toward digital transformation of administrative services. The policy pressure is also indirect but equally real: RA 10931 removed tuition barriers in public higher education, which increased admission volumes at every SUC including ISPSC. More applicants + same paper process = mounting operational strain. CHED does not mandate a specific system, but its digitalization directive combined with the volume pressure from RA 10931 creates the institutional need that NEXIAM addresses.

---

### TRAP 9: The "Slade and Prinsloo — Why Cite Learning Analytics?" Question [HIGH]

**Q:** "Slade and Prinsloo (2013) is about learning analytics ethics, not admission systems. Why is it in your Background?"

**David:**
> Because their ethical framework applies beyond learning analytics. They established principles for handling student data in digital systems: transparency about how data is used, the duty to protect data when processed externally, and the obligation not to reduce students to algorithmic profiles. All three principles shaped NEXIAM. The K-Means module surfaces patterns for counselor review — it does not auto-classify applicants. The RAG chatbot is transparent about its knowledge boundaries. And data processed through external AI inference is governed by the same RBAC isolation as local data. Slade and Prinsloo gave us the ethical vocabulary for a system that processes applicant information.

---

### TRAP 10: The "Lewis RAG — Explain It Simply" Question [MEDIUM]

**Q:** "You cite Lewis et al. (2020) for RAG. How does retrieval-augmented generation actually prevent hallucinations in your chatbot?"

**David:**
> A standard LLM chatbot answers questions from its training data — which may be outdated, incomplete, or flat wrong about ISPSC policies. A RAG chatbot works differently. When an applicant asks about admission requirements, the system first searches a curated knowledge base of institutional policy documents. If it finds relevant text, it uses that text as the ground truth for its response. If no document matches above a confidence threshold, it says: "I do not have that information." It does not guess. The retrieval step anchors the response to actual ISPSC documents rather than the model's internal knowledge.

---

### TRAP 11: The "Proctor Role — Is It 7 or 8?" Question [HIGH]

**Q:** "Your manuscript says seven user roles but the use case diagram shows eight actors. Which is correct?"

**David:**
> Both are correct, and the distinction is intentional. NEXIAM defines seven distinct RBAC roles: Applicant, Program Head, Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. The Proctor is not a separate eighth role — it is a duty overlay assignable to any existing staff role at administrative discretion. A Guidance Counselor assigned as Proctor temporarily gains the proctoring interface permissions while retaining their base role. The use case diagram shows Proctor as an eighth actor because it is a distinct system interaction, but architecturally it sits on top of the seven-role RBAC structure as an overlay, not a permanent role.

---

### TRAP 12: The "Esquivel — How Does Enrollment Prediction Justify Admissions?" Question [MEDIUM]

**Q:** "Esquivel and Esquivel (2020) predicted enrollment from admission data. But your system is about admission testing, not enrollment. Why cite them?"

**David:**
> Because enrollment predictability proves that program quotas are not static numbers — they are forecasts that affect resource allocation. When admissions are slow, enrollment projections drift. Esquivel demonstrated that enrollment likelihood can be modeled from admission-stage data at a Philippine university. This means Program Heads need visibility into applicant volumes and course preferences during the admission cycle — not after. That visibility is what the pre-enrollment slate output in our IPO provides. Esquivel justifies a specific system requirement: quota visibility before enrollment begins.

---

### TRAP 13: The "Ruffalo Noel Levitz Is a Marketing Firm" Question [ELITE]

**Q:** "Ruffalo Noel Levitz is a marketing consultancy, not an academic source. Why cite them in Chapter 1?"

**David:**
> They published an industry report based on actual student behavior data showing that speed of processing directly affects applicant yield — students commit to whichever institution processes their application faster. It is practitioner evidence, not academic research, and we cite it for exactly what it is: empirical observation from the enrollment management industry. The academic sources — French et al., Sánchez-Mendiola et al., Liu et al. — carry the scholarly weight. Ruffalo Noel Levitz adds the practitioner dimension: this is not just a theoretical problem, it has measurable operational consequences.

---

### TRAP 14: The "Dash et al. Only Went to Design Stage" Question [MEDIUM]

**Q:** "You cite Dash et al. (2026) to say they stopped at design while you go to deployment. But Dash is the closest precedent to NEXIAM. Why should we believe you'll succeed where they didn't?"

**David:**
> Dash et al. validated the concept — a unified AI-assisted admission platform is architecturally feasible. They stopped at design, which means the research community has acknowledged the gap but has not shipped. Our advantage is methodology. Dash used a traditional development approach. We use AI-augmented RAD, where AI agents handle routine implementation work and the team focuses on architecture and integration. The same methodology that allows a three-person team to build enterprise layers in four months is what Dash lacked. Concept proven, methodology advanced — that is the difference.

---

## SECTION 2: JAYPEE — Scope, Development, Deployment

---

### TRAP 15: The "Four Months Is Unrealistic" Question [CRITICAL]

**Q:** "You're building OMR, RAG chatbot, K-Means, RBAC, AI scheduling, a full applicant portal, and audit trails — in four months with three people. How is this timeline realistic?"

**Jaypee:**
> Four things make this timeline achievable. First, every component uses existing, published implementations — we are integrating proven building blocks, not building from scratch. Second, the AI-augmented RAD methodology eliminates the traditional bottleneck of manual syntax generation. Third, our project plan allocates the largest continuous block to Rapid Construction — early July through August, with iterative back-and-forth between design refinement and construction. Fourth, the team has cross-functional dual roles that keep momentum continuous — when one member is on academic load, another moves construction forward.
>
> We acknowledge the timeline is tight. The scope is prioritized: core functionality over feature breadth where time is constrained. And free-tier AI inference is a documented limitation that bounds concurrent request volume.

---

### TRAP 16: The "Offline Proctoring + Cloud = Contradiction" Question [CRITICAL]

**Q:** "You say the system is cloud-hosted but the proctoring interface works offline. How do you reconcile those two?"

**Jaypee:**
> They serve different operational realities. ISPSC Tagudin Campus has reliable network connectivity concentrated in designated office areas — the Registrar, Guidance, and Program Head offices. The examination venues, however, may have limited or no reliable connectivity. NEXIAM is cloud-hosted for all system functions outside the proctoring interface. The proctoring interface alone uses service-worker caching to maintain examination session state during connectivity loss. When the connection restores, cached data synchronizes to the cloud database.
>
> This is not a contradiction — it is a design response to a documented infrastructure reality. The manuscript scope section explicitly states this.

---

### TRAP 17: The "What Happens If the Service Worker Fails?" Question [HIGH]

**Q:** "Service-worker caching has failure modes. What if the proctor's browser crashes mid-exam during an offline period?"

**Jaypee:**
> Service-worker caching persists across browser sessions — a crash does not wipe the cache. The exam session state is serialized to the cache on every state change. On browser restart, the service worker restores the last saved state. The risk is data loss between the last save and the crash, which for a multi-choice exam interface means at most one question's response. We acknowledge this as a reliability trade-off inherent to any offline-first web architecture. Formal redundancy — such as local database replication — is identified as future work outside this study's scope.

---

### TRAP 18: The "Free-Tier AI Limits — How Many Concurrent Users?" Question [MEDIUM]

**Q:** "You say free-tier API access bounds inference requests. What's the actual limit? What happens during peak load?"

**Jaypee:**
> The exact rate limit depends on the provider's free-tier policy, which can change. We acknowledge this as a documented limitation. The system queues inference requests — RAG chatbot queries, schedule optimization runs — and processes them sequentially. For the evaluation phase with 47 simulated evaluators, sequential processing is sufficient because evaluators do not all interact simultaneously. For a production admission cycle with hundreds of concurrent applicants, paid inference capacity would be required. That is outside this study's scope and identified as a deployment recommendation.

---

### TRAP 19: The "Why RAD Instead of Agile?" Question [HIGH]

**Q:** "You chose RAD. Why not Scrum or Agile? Isn't RAD outdated?"

**Jaypee:**
> RAD is not outdated — it is specifically validated for Philippine SUC system deployments. Olipas (2020) used RAD for a student information system at a Philippine HEI. Melendres and Aranda (2024) used it for a web-based resident information system. Both studies confirm RAD's functional suitability and security outcomes in our exact deployment context.
>
> The four-phase RAD structure also maps cleanly to our descriptive-developmental research design: Requirements Planning serves the descriptive phase (SO1, SO2), while User Design through Cutover serves the developmental and evaluation phases (SO2, SO3, SO4). Agile's continuous iteration would complicate the clear phase-to-objective mapping required for capstone documentation.

---

### TRAP 20: The "AI Writes the Code — What Did You Actually Do?" Question [CRITICAL]

**Q:** "If AI generates most of your code, what is your contribution as developers?"

**Jaypee:**
> AI writes the routine implementation — boilerplate, CRUD operations, standard UI components. Our team does five things AI cannot do. One: we define the architecture — the RBAC schema, the authorization matrix, the adapter layer for swappable ML engines. Two: we write the specifications that ground AI output in ISPSC's specific operational reality, not generic solutions. Three: we conduct rigorous peer code reviews on every AI-generated module — AI writes, humans decide what ships. Four: we build the complex integration layers — connecting OMR to the database, wiring RAG to institutional documents, linking K-Means output to the Guidance dashboard. Five: we test, validate, and iterate against actual staff feedback.
>
> The methodology is AI-augmented, not AI-replaced. The review-and-integrate gate in Figure 2 enforces this.

---

### TRAP 21: The "Why Cloud-Hosted? Why Not On-Premises?" Question [MEDIUM]

**Q:** "Your manuscript says cloud hosting was chosen due to 'local campus hardware constraints.' What constraints specifically?"

**Jaypee:**
> ISPSC Tagudin Campus does not maintain dedicated server infrastructure for web application hosting at the scale NEXIAM requires. Deploying on-premises would require procuring, configuring, and maintaining hardware — cost and timeline factors outside a capstone project's resources. Cloud hosting on a Dokploy-managed VPS gives us production-grade deployment with containerized isolation at a fraction of the cost and setup time. The trade-off is that all non-proctoring functions require internet connectivity. That trade-off is explicitly documented in the scope.

---

### TRAP 22: The "September Evaluation — No Real Applicants" Question [HIGH]

**Q:** "Your evaluation happens outside the admission cycle with simulated evaluators. How can you claim the system works for real admissions?"

**Jaypee:**
> We don't claim it works for real admissions — we claim it is acceptable to the people who would operate it during a real admission. The simulated evaluators are the exact same staff who manage the real admission cycle — Guidance personnel, Registrar staff, Program Heads. They are evaluating NEXIAM against the manual workflow they actually know. The first-year students just experienced the real admission process months earlier — their memory of the experience is fresh.
>
> The limitation is acknowledged: results are bounded by simulated operational conditions. Live-applicant validation during an actual admission cycle is identified as future work. What we can establish now is whether the system is acceptable to the institutional stakeholders who would use it.

---

### TRAP 23: The "Code Review by Teammates Isn't Independent" Question [MEDIUM]

**Q:** "Your peer code review is done by the same three-person team that wrote the code. Isn't that review biased?"

**Jaypee:**
> The project assignments in Table 1 formalize the separation. QA testing is assigned exclusively to Christine — she does not write production code. When Jaypee's AI-generated modules come in, Christine tests them. When David's architectural components ship, Jaypee and Christine review them. The dual-role structure creates overlap: every module gets eyes from at least two people, and the QA tester is structurally separated from construction.
>
> We acknowledge this is not the same as independent third-party review. For a three-person academic team, formal QA isolation from construction is the strongest feasible independence structure.

---

### TRAP 24: The "Proctoring Is Not a Full Offline System" Question [ELITE]

**Q:** "Your offline resilience only covers the proctoring interface. What if the cloud server goes down during an exam and the applicant portal, scoring, and chatbot all become unavailable? The exam continues but nothing else works."

**Jaypee:**
> Correct — and that is by design. The proctoring interface is the only component that must function during an active examination in a potentially disconnected venue. The applicant portal, scoring engine, and chatbot serve functions that occur before and after the exam, not during it. An applicant does not need the chatbot while sitting for the exam. The Registrar does not need the scoring engine until after answer sheets are collected. The architecture isolates offline resilience to the component that actually faces connectivity loss during its critical window.
>
> Full system-wide offline resilience would require local server infrastructure, which is outside both the project's scope and the campus's current hardware capability.

---

## SECTION 3: CHRISTINE — Methodology, Statistics, TAM, Bias

---

### TRAP 25: The "Descriptive-Developmental vs Quasi-Experimental" Question [CRITICAL]

**Q:** "You use a pre-test/post-test with paired t-test. That is quasi-experimental, not descriptive-developmental. Why label it descriptive-developmental?"

**Christine:**
> The research design is classified by what the study primarily does, panelist, not by one statistical test in one phase. Our study does three things in sequence. One: describe the existing admission process and pinpoint its operational gaps — descriptive inquiry, thematic analysis, no manipulation. Two: develop NEXIAM to address those gaps — iterative construction with staff validation, no experimental groups. Three: evaluate whether the developed system improves upon the manual baseline — the paired TAM. The pre-test/post-test is an evaluation tool within the developmental phase, not an experimental manipulation. We are not randomly assigning groups, manipulating an independent variable, or controlling extraneous variables. We are measuring a within-subjects perception shift after introducing a new system. The classification follows Frey's (2022) descriptive-developmental framework, which accommodates evaluation instruments as part of the developmental process.

---

### TRAP 26: The "Why No Control Group?" Question [CRITICAL]

**Q:** "Why not split your 47 evaluators into control and experimental groups for a stronger design?"

**Christine:**
> Structurally impossible at this site for two reasons. First, the staff complement is too small to split. The Guidance Office has exactly two personnel and the Registrar Office has three. Splitting 2-3 people gives n=1-2 per group — no statistical power, meaningless comparison. Second, NEXIAM replaces the admissions workflow for the entire office. You cannot have half the staff on paper and half on the same system processing the same applicants — the workflow is singular.
>
> The within-subjects paired design is the correct tool for this constraint: each evaluator serves as their own control. We measure the same person's perception of the manual process (pre-test) and NEXIAM (post-test) on the same construct. That delta — the shift within each person — answers the research question directly.

---

### TRAP 27: The "Apples to Oranges" Question [CRITICAL]

**Q:** "Your pre-test measures the manual process. Your post-test measures NEXIAM. You are comparing two completely different things. How is that valid?"

**Christine:**
> That is precisely the point of Specific Objective 4, panelist. NEXIAM is designed to replace the manual process for the same function. The only meaningful comparison is between the old method and the new — experienced by the same type of user performing the same type of task. If we only measured NEXIAM post-test scores, we would know absolute acceptance. By anchoring with the pre-test, we measure relative improvement.
>
> Both instruments measure the same four TAM constructs — perceived usefulness, perceived ease of use, attitude, and behavioral intention — using the same 7-point Likert scale. The referent changes (manual process vs. NEXIAM) but the measurement framework is identical. A before-and-after comparison of two tools serving the same function is standard in technology adoption research. The apples are different varieties, but they grow in the same orchard.

---

### TRAP 28: The "TAM Pre-Test Breaks Protocol" Question [HIGH]

**Q:** "Davis designed TAM for post-use measurement only. Administering it before anyone touches NEXIAM breaks the instrument's protocol."

**Christine:**
> The pre-test does not measure pre-adoption perception of NEXIAM — that would indeed break protocol. The pre-test measures evaluator perception of the existing manual admission process across the same four TAM constructs. The prompt is: "Rate the current admission process" — not "Rate what you expect NEXIAM to be."
>
> The post-test then measures NEXIAM on the same constructs. The delta captures the shift from manual to automated for the same individual. A single post-test would only tell us whether NEXIAM is acceptable in absolute terms. It would not tell us whether NEXIAM is measurably better than what evaluators were using before — which is SO4's research question. The 7-point Likert scale and within-subjects design are well-established for this kind of comparative evaluation in information systems research.

---

### TRAP 29: The "n=46 Is Too Small" Question [HIGH]

**Q:** "You have n=46 for your paired t-test. The conventional threshold for parametric tests is 30 per group. Are you underpowered?"

**Christine:**
> The 30-per-group threshold applies to independent samples designs, not paired designs. In a paired design, the effective sample is the number of pairs — here, n=46 paired observations. The Shapiro-Wilk test gates the normality assumption: if the paired differences are normally distributed, the paired t-test is valid regardless of n=46.
>
> We also report effect size — Cohen's d or the rank-biserial correlation — not just p-values. Effect size tells the panel how large the improvement is, not just whether it is statistically significant. If normality is violated, the Wilcoxon signed-rank test is the non-parametric fallback. The data decides the statistical path, not us. This two-gate approach — normality check first, then parametric or non-parametric — is the correct procedure for small-to-moderate paired samples.

---

### TRAP 30: The "Purposive Sampling Bias" Question [HIGH]

**Q:** "You used purposive sampling. Your evaluators were hand-picked. How do you control for selection bias inflating your TAM scores?"

**Christine:**
> Purposive sampling is not the same as convenience sampling. We did not pick evaluators who would like NEXIAM — we selected evaluators for direct operational relevance. The Guidance and Registrar staff are the complete available complement of those offices — every person who does the job. The 11 Program Heads represent all academic departments. The 30 first-year students have just experienced the real admission process.
>
> Selection bias would mean we chose evaluators predisposed to rate NEXIAM favorably. But the pre-test establishes each evaluator's baseline perception of the manual process — giving us a within-subject control. If an evaluator is a harsh rater, they rate both the manual process and NEXIAM harshly. The paired delta accounts for individual response tendencies. This is precisely why the within-subjects design is stronger than a post-test-only between-groups design for this evaluation context.

---

### TRAP 31: The "Why 7-Point Likert Instead of 5?" Question [MEDIUM]

**Q:** "Why did you choose a 7-point Likert scale?"

**Christine:**
> A 7-point scale provides greater variance than a 5-point alternative for detecting the pre-to-post perception shift that SO4 measures. With 5 points, respondents tend to cluster around the midpoint, reducing the detectable effect size. Seven points give more granular discrimination — important when you are measuring a within-subjects shift where the expected improvement may be moderate rather than dramatic.
>
> Seven-point scales are standard in TAM research — Davis (1989) originally used a 7-point Likert scale. We follow the instrument's validated specification.

---

### TRAP 32: The "16-Item TAM — But 4 Roles = 64 Items" Question [HIGH]

**Q:** "You say 16-item TAM, but with 4 respondent groups and 4 items per construct, you have 64 unique item phrasings across your tables. Which is it — 16 or 64?"

**Christine:**
> The TAM instrument itself has 16 items — 4 constructs × 4 items each. Those 16 items are role-adapted: the same construct is measured across all four respondent groups, but the phrasing is tailored to each group's operational reality. A Registrar sees "NEXIAM improves my ability to track applicant records." A Guidance counselor sees "NEXIAM improves my ability to evaluate applicant profiles."
>
> Both items measure Perceived Usefulness — the same TAM item, adapted for context. The psychometric properties are preserved because the underlying construct being measured is identical. We report it as a 16-item instrument because the adaptation is phrasing-level, not construct-level. The manuscript's Glossary defines it this way: "NEXIAM's 16-item TAM instrument is administered as both a Pre-Test and Post-Test on a 7-point Likert scale, with items adapted to the operational realities of each respondent role."

---

### TRAP 33: The "IT Expert — Why Separate Evaluation?" Question [MEDIUM]

**Q:** "Why is the IT expert excluded from TAM and evaluated separately?"

**Christine:**
> TAM measures end-user acceptance — perceived usefulness, ease of use, attitude toward use, behavioral intention to use. An IT expert evaluating system architecture, RBAC implementation, security posture, and maintainability is not an end user in the TAM sense. The constructs do not fit their evaluation criteria. Asking an IT expert to rate "NEXIAM improves my attitude toward my work" on a Likert scale would not capture what we need from them.
>
> Instead, the IT expert conducts a structured technical walkthrough covering architecture soundness, security posture, data privacy compliance, and maintainability. Findings are reported qualitatively alongside the TAM results as supplementary expert validation. Because n=1, no inferential statistics are applied. This separation is documented in the Research Instruments and Data Analysis sections.

---

### TRAP 34: The "Shapiro-Wilk on n=46 — Won't It Always Pass?" Question [ELITE]

**Q:** "Shapiro-Wilk on small samples has low power to reject normality. Your n=46 is borderline. If the test fails to reject, you use the t-test. But what if the data is actually non-normal and Shapiro-Wilk just missed it?"

**Christine:**
> This is a valid concern, panelist. At n=46, Shapiro-Wilk has moderate power — it will detect gross departures from normality but may miss subtle violations. We have two safeguards. First, we report the Shapiro-Wilk statistic and p-value explicitly so the panel can assess the evidence for normality themselves. Second, we also report effect size — Cohen's d for the t-test, or the matched-pairs rank-biserial correlation for Wilcoxon. Effect size is robust to distributional assumptions and provides a meaningful measure of practical significance regardless of which test path is taken.
>
> If the panel prefers, we can default to Wilcoxon as the primary test and report the t-test as supplementary — because Wilcoxon makes no distributional assumptions. Either path answers SO4: is there a significant difference between pre and post?

---

### TRAP 35: The "Effect Size — Why Is It Important?" Question [MEDIUM]

**Q:** "Why are you reporting effect size in addition to p-values?"

**Christine:**
> A significant p-value tells you a difference exists. Effect size tells you how big that difference is — whether the improvement is practically meaningful or merely statistically detectable. A paired t-test with n=46 could produce a significant p-value for a trivially small improvement. Cohen's d or the rank-biserial correlation quantifies the magnitude so we can say whether NEXIAM is not just statistically better than the manual process but meaningfully better. This is standard reporting practice recommended by the APA and required for a complete statistical analysis.

---

### TRAP 36: The "Thematic Analysis — Who Validates the Themes?" Question [HIGH]

**Q:** "SO1 uses thematic analysis on interview responses. How do you ensure the themes you extract are valid and not just what you wanted to find?"

**Christine:**
> The themes are not extracted in isolation — they are validated against two independent evidence sources. First, field observation notes supply procedural context that questionnaire data alone cannot capture. If a staff member claims scoring takes five days but field observation documents a two-week cycle at peak, the theme captures the range. Second, extracted themes are brought back to the Guidance and Registrar offices for confirmation: "This is what we heard. Is this accurate?" Only confirmed themes become functional requirements.
>
> The project plan enforces this — Requirements Planning concludes only after findings are confirmed directly with the offices that experience them. This is not armchair coding. It is fieldwork triangulated against staff validation.

---

### TRAP 37: The "SO2 Requirements — How Do You Know They're Complete?" Question [MEDIUM]

**Q:** "SO2 determines system requirements. How do you know you haven't missed a critical requirement?"

**Christine:**
> We don't claim completeness — we claim traceability. Every NEXIAM feature traces to a documented operational failure from SO1. The requirement is complete to the extent that SO1 captured the operational reality. Two constraints bound completeness risk. One: the Guidance and Registrar offices are small — 5 staff total — so the pool of pain points is finite and accessible. Two: iterative validation during the User Design phase catches gaps. If a demonstrated module reveals a missing requirement, it is logged as a revision item and added. The functional requirements table is a living document during development, finalized only at Cutover.

---

### TRAP 38: The "TAM Means Above 4.0 — Is That Really Acceptance?" Question [HIGH]

**Q:** "You define acceptance as a pooled TAM mean above 4.0 on a 7-point scale. But 4.0 is barely above neutral. Shouldn't acceptance require a higher threshold?"

**Christine:**
> The 4.0 threshold is the scale midpoint — it separates positive from negative perception. It is the standard interpretive benchmark in TAM literature, not a bar we invented. We report the exact means and standard deviations per construct per role so the panel can assess the strength of acceptance directly — not just whether it cleared a single threshold.
>
> If a construct mean is 4.2 with a wide standard deviation, that is weak acceptance with high variability. If it is 6.1 with a narrow standard deviation, that is strong, consistent acceptance. The numbers are reported transparently. The 4.0 threshold is the floor, not the ceiling. The paired t-test in SO4 provides the complementary answer: regardless of where the absolute means fall, is NEXIAM significantly higher than the manual baseline?

---

### TRAP 39: The "Why Only PU and PEOU in SO4?" Question [MEDIUM]

**Q:** "Your null hypotheses only test Perceived Usefulness and Perceived Ease of Use. Why not Attitude and Behavioral Intention?"

**Christine:**
> PU and PEOU are the two core constructs of the original TAM — Davis (1989) established them as the primary determinants of technology acceptance. ATU and BIU are downstream constructs that mediate the PU/PEOU → usage relationship. Testing all four would inflate the familywise error rate with four separate paired tests without a clear methodological benefit.
>
> If PU and PEOU both show significant improvement, the downstream constructs (attitude, intention) logically follow — because the TAM literature has established that causal chain through decades of validation. Testing PU and PEOU directly answers the operational question: is NEXIAM more useful and easier to use than the manual process? Those are the constructs that map to the documented failures.

---

### TRAP 40: The "First-Year Students Aren't Real Applicants" Question [HIGH]

**Q:** "Your 'Applicant' group is first-year students who already got in. They are not real applicants making real decisions. Doesn't this invalidate their evaluations?"

**Christine:**
> They just completed the real admission process months earlier — the experience is fresh and authentic. They know exactly what the manual process felt like because they lived it: the trips to campus, the Facebook page monitoring, the waiting. That direct experience is actually more valuable than a hypothetical applicant who has never gone through any admission process.
>
> The limitation is acknowledged: results are bounded by simulated operational conditions during the September evaluation window. A first-year student evaluating NEXIAM's portal in September is not the same as a real applicant using it during a live admission cycle. But for measuring the shift from a known manual experience to a new digital one, first-year students with fresh institutional memory are the strongest available proxy for actual applicants.

---

### TRAP 41: The "Program Heads — 11 Evaluators for What?" Question [MEDIUM]

**Q:** "You have 11 Program Heads. What exactly do they evaluate? They don't process applications."

**Christine:**
> Program Heads evaluate two specific NEXIAM outputs: pre-enrollment slates and the decision-support triage. In the manual process, Program Heads receive informal text messages from Guidance about incoming applicant volumes and course preferences — unstructured, delayed, and incomplete. NEXIAM's pre-enrollment slate gives them a structured dashboard showing how many applicants are targeting each program, with K-Means surfaced aptitude patterns as supplementary context.
>
> They also evaluate the decision-support triage because they are the ones who set local program qualification standards. When an applicant's aptitude pattern suggests a mismatch with their first-choice program, the Program Head sees the triage note alongside the counselor's placement recommendation. Their evaluation matters because they are the downstream consumers of the admission data.

---

## SECTION 4: WEAK POINTS — Acknowledge, Don't Defend

These are limitations the manuscript already acknowledges. When asked, name the section, acknowledge, and move on. Do NOT defend.

1. **"Simulated evaluators limit external validity"** → Acknowledged in Scope: "results are accordingly bounded by simulated operational conditions." Future work: live-applicant validation during an actual admission cycle.

2. **"No formal NPC certification"** → Acknowledged in Scope: "formal National Privacy Commission certification and Privacy Impact Assessment fall outside this study's scope."

3. **"Free-tier AI inference bounds concurrent requests"** → Acknowledged in Scope: "paid or dedicated inference capacity is outside this study's scope."

4. **"The Osmani whitepaper is on Kaggle, not a peer-reviewed Google publication"** → Acknowledge it is practitioner evidence, not peer-reviewed research. The manuscript also cites Peng et al. (2023) for the peer-reviewed productivity-gain evidence.

5. **"n=46 for TAM, n=1 for IT expert — small sample sizes"** → Acknowledged in Data Analysis: "the sample size is relatively small (n=46)." The Shapiro-Wilk gate, Wilcoxon fallback, and effect size reporting are all responses to this limitation.

6. **"Dash et al. published in Istoriya Journal — verify journal quality"** → If challenged on journal credibility, acknowledge and pivot to the stronger precedents (Cuerdo, Dayata, Tan, Yukee).

---

## SECTION 5: TEAM DRILL PATTERN

**Phase 1: Individual (30 min each)**
- Each person reads their section traps aloud three times
- Goal: answers feel natural, not memorized

**Phase 2: Pair Drill (20 min per pair)**
- One person fires random traps at the other from any section
- Receiver must answer within 30 seconds
- Swap roles

**Phase 3: Full Run (30 min)**
- Designate one person as "panelist" — they fire traps randomly
- Practice cross-domain pass-off: "I can speak to how the [system aspect] works, but for [specific evaluation aspect] I defer to [teammate], our [role]."
- Time the full Q&A end-to-end (target: 20-30 minutes of Q&A)

**Pass-Off Phrases:**
- David → Christine: "For the statistical methodology behind that claim, I defer to Christine, our QA Tester and Data Analyst."
- Christine → Jaypee: "For the technical architecture of that component, I defer to Jaypee, our Lead Developer."
- Jaypee → David: "For the legal and policy framework, I defer to David, our Project Manager."

---

## SECTION 6: CRITICAL FIRST 5 MINUTES

The first questions after the presentation end will set the tone. Memorize these three:

1. **"Why so many features?"** → David (Trap 2) — feature-to-failure mapping, integration contribution, bounded scope
2. **"This is quasi-experimental, not descriptive-developmental"** → Christine (Trap 25) — primary activity classification, evaluation tool vs. experimental manipulation
3. **"Why no control group?"** → Christine (Trap 26) — staff too few to split, system replaces workflow entirely, within-subjects is the correct design

If the panel opens with a methodology attack, Christine must be ready to speak first. If they open with a scope/features attack, David takes it.

---

*Generated from manuscript (NEXIAM.pdf), speaker script (v4), and presentation slides. 41 traps covering Background, Architecture, Scope, Development, Methodology, Statistics, and Policy. Add traps from subagent findings (reference verification, IPO mapping, panel pattern research) for the final v2.*
