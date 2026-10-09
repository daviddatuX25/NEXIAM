# NEXIAM Proposal Defense — Q&A Defense Bank (v2 — FINAL)

**Coverage:** ~60 traps across 3 presenters  
**Basis:** Manuscript (NEXIAM.pdf), Speaker Script (v4), Presentation Slides, Legacy Q&A Bank (v4)  
**Convention:** `[CRITICAL]` = guaranteed; `[HIGH]` = likely; `[MEDIUM]` = sharp panelist; `[ELITE]` = rare but devastating

---

## PRE-FLIGHT: Document Fixes Before Drill

Apply these before team practice. Panelist comparing documents catches these instantly.

### Script (v4) — FIXED ✓
- Slide 10: "proxy evaluators" → "simulated evaluators drawn through purposive sampling" — DONE

### Presentation Text (.txt proxy for ODP) — FIXED ✓
- Line 119: "proxy evaluators" → "simulated evaluators drawn through purposive sampling" — DONE
- Line 195: "Applicant Proxies" → "First-Year Students" — DONE  
- Line 205: "Applicant Proxy (n=30)" → "First-Year Students (n=30)" — DONE
- Line 224: TAM group list now correctly excludes IT Expert — DONE

### Presentation Slides (ODP) — MANUAL FIXES NEEDED
- Slide 6 (Scope): Change "proxy evaluators" to "simulated evaluators"
- Slide 11 (Population): Change "Applicant Proxies" to "First-Year Students"

### Manuscript (ODT) — INFORM ADVISER
- Reference list: Woelert (2023) appears twice. Remove duplicate.
- Reference list: Villarino entry DOI bleeds into Woelert entry on same line. Add line break.

---

## SECTION 1: DAVID — Background, Gap, Architecture, Legal

---

### TRAP 1: The "So What's the Gap?" Question [CRITICAL]

**Q:** "What exactly is the research gap? Aren't there already admission systems?"

> The gap is integration, not invention. Every component NEXIAM needs has been proven independently. Dayata and Yap deployed a RAG chatbot at USC, published at IEEE COMNETSAT. Cuerdo validated low-cost OMR in Philippine public schools. Tan proved K-Means for course routing at an ACM conference. Yukee et al. ran K-Means on ISPSC's own ICAT data.
>
> What does not exist is one governed pipeline that runs them together — scoring, aptitude assessment, classification, and structured guidance — inside a role-based architecture that the Guidance Office and Registrar can actually operate during an admission season. Dash et al. (2026) proposed a similar unified platform at the design stage and stopped there, confirming the gap is in deployment and governance, not in concept.

---

### TRAP 2: The "Why So Many Features?" Question [CRITICAL] — memorize cold

**3-part answer:**

**Part A — Each feature maps to a documented failure:**
> Each feature traces to a documented operational failure at ISPSC. Hand-scoring takes five days to two weeks — that maps to OMR. Applicants monitor a Facebook page during office hours — that maps to the portal. Inter-office coordination runs on unstructured text messages — that maps to RBAC with shared dashboards. RA 10173 requires data governance paper cannot provide — that maps to audit trails. Remove any feature and a documented failure persists.

**Part B — Integration is the contribution:**
> The contribution is not inventing OMR or K-Means. These are established techniques, each validated independently in published literature. The novel element is proving that a single governed pipeline can integrate them under one RBAC framework and empirically outperform the manual method. That is the research question SO4 answers.

**Part C — The scope is bounded:**
> Enrollment processing, tuition management, long-term academic records, and cross-campus resource allocation are all explicitly excluded. The manuscript scope section names what is outside. The study covers only the admission testing function.

---

### TRAP 3: The "Aren't These Features Too Advanced for Undergrads?" Question [CRITICAL]

**Q:** "OMR with computer vision, RAG chatbots, K-Means — isn't this graduate-level work?"

> Every component is borrowed from existing, published implementations. We are not inventing new algorithms. OMR builds on Cuerdo's validated approach for Philippine public schools. RAG replicates Dayata and Yap's architecture from their IEEE paper. K-Means uses Yukee's already-validated model on ISPSC's own ICAT data. We are integrating proven building blocks.
>
> The AI-augmented RAD methodology further accelerates construction. AI generates routine implementation work; our team focuses on architecture and integration. The human Review and Integrate gate ensures every AI-produced artifact is vetted before merging.

---

### TRAP 4: The "Lira Doesn't Prove Your Case" Question [HIGH]

**Q:** "Lira et al. assessed personal qualities through essays — not admission testing. How does that apply?"

> Lira proves the high-water mark: AI-based applicant assessment is validated at Science Advances, the highest level of peer review. Their finding that ML models can extract predictive signal from unstructured materials establishes that AI in admissions is not speculative. We are not replicating Lira's methodology. We apply AI to the operational infrastructure layer — scoring, scheduling, applicant classification — rather than the psychometric layer. Lira opens the door for AI in admissions. NEXIAM walks through that door on the operational side.

---

### TRAP 5: The "Van Busum and Fang Contradicts You" Question [HIGH]

**Q:** "You cite Van Busum and Fang on AI bias in admissions. If AI models can be biased, why build a system with AI components?"

> Precisely because bias is real — which is why we designed NEXIAM around that finding, not against it. Three safeguards: the RAG chatbot is restricted to institutional policy documents — if no document matches above a confidence threshold, it says it does not have the information rather than fabricating. K-Means surfaces aptitude patterns for counselor review — it does not make admission decisions. The algorithm proposes; the counselor disposes. Every AI-assisted output passes through a human-gated approval step before taking effect.
>
> Van Busum and Fang's finding is not a reason to avoid AI. It is the design requirement that shaped our architecture.

---

### TRAP 6: The "RA 10173 Compliance — Prove It" Question [CRITICAL]

**Q:** "You claim NEXIAM aligns with the Data Privacy Act. How, specifically? Why no NPC certification?"

> Three specific mechanisms. RBAC-enforced data isolation: each role accesses only the data its office is authorized to see. HMAC-SHA256 audit trails: every data access event is cryptographically chained, making any tampering structurally detectable. RAG chatbot draws only from institutional policy documents, preventing external data leakage.
>
> Formal NPC certification and the Privacy Impact Assessment are identified as outside scope. We designed to RA 10173's requirements at the application layer and state this limitation explicitly. Full regulatory certification requires a process separate from system development — it is future work we acknowledge.

---

### TRAP 7: The "Why ISPSC Specifically?" Question [MEDIUM]

**Q:** "Your local studies — Yukee, Ballesteros, Eustaquio — are all from ISPSC. Isn't that circular?"

> That is exactly the point. A system built for ISPSC must be grounded in ISPSC's operational reality. Yukee validated K-Means on ISPSC's own ICAT entrance exam data — we know the algorithm works on our actual applicant population. Eustaquio demonstrated that a single digital platform can support multiple administrative functions on this campus. The local studies are not circular reasoning — they are site-specific validation. The global and national literature establishes that the approach works in general. The local studies establish that it will work here.

---

### TRAP 8: The "Adwan Is GCC — Why Relevant?" Question [HIGH]

**Q:** "Adwan et al. studied GCC universities in the Middle East. How is a mobile admission app in the Gulf region relevant to a Philippine provincial state college?"

> The relevance is architectural, not contextual. Adwan demonstrated that a unified, centralized admission platform can serve multiple institutions with 97% usability under Nielsen heuristics. This proves the integration model itself is deployable — that combining admission functions into one platform is technically feasible. The specific cultural context differs, but the architectural precedent is directly applicable: unified platforms work across institutions.

---

### TRAP 9: The "CHED Policies" Question [MEDIUM]

**Q:** "You mention CHED's strategic plan. What specific CHED policy mandates a system like NEXIAM?"

> CHED's 2022-2028 Strategic Plan directs SUCs toward digital transformation of administrative services. The policy pressure is also indirect but equally real: RA 10931 removed tuition barriers, increasing admission volumes at every SUC including ISPSC. More applicants plus the same paper process equals mounting operational strain. CHED does not mandate a specific system, but its digitalization directive combined with RA 10931's volume pressure creates the institutional need NEXIAM addresses.

---

### TRAP 10: The "Slade and Prinsloo — Why Learning Analytics Ethics?" Question [HIGH]

**Q:** "Slade and Prinsloo is about learning analytics, not admission systems. Why cite them?"

> Because their ethical framework applies beyond learning analytics. They established three principles for handling student data in digital systems: transparency about data use, the duty to protect externally processed data, and the obligation not to reduce students to algorithmic profiles. All three shaped NEXIAM. K-Means surfaces patterns for counselor review — it does not auto-classify applicants. The RAG chatbot is transparent about its knowledge boundaries. Data processed through external AI inference is governed by the same RBAC isolation as local data.

---

### TRAP 11: The "Lewis RAG — Explain It Simply" Question [MEDIUM]

**Q:** "How does retrieval-augmented generation actually prevent hallucinations in your chatbot?"

> A standard LLM chatbot answers from its training data — which may be outdated, incomplete, or wrong about ISPSC policies. A RAG chatbot works differently. When an applicant asks about admission requirements, the system first searches a curated knowledge base of ISPSC policy documents. If it finds relevant text, it uses that as ground truth for its response. If no document matches above a confidence threshold, it says: "I do not have that information." It does not guess. The retrieval step anchors the response to actual institutional documents.

---

### TRAP 12: The "Why RAG vs Fine-Tuning?" Question [MEDIUM]

**Q:** "Why RAG instead of fine-tuning a local model on ISPSC documents?"

> Fine-tuning is static and expensive. If the administration changes a deadline or program quota, a fine-tuned model needs retraining to learn the new fact. With RAG, we simply update the text in our vector database — the chatbot immediately retrieves the new rule on the next query. RAG ensures real-time accuracy without the overhead of continuous model training, which is critical during an active admission cycle where policy changes occur.

---

### TRAP 13: The "Proctor Role — 7 or 8?" Question [HIGH]

**Q:** "Your manuscript says seven user roles but the use case diagram shows eight actors. Which is correct?"

> Both are correct, and the distinction is intentional. NEXIAM defines seven distinct RBAC roles: Applicant, Program Head, Guidance Counselor, Guidance Administrator, Registrar Staff, Registrar Administrator, and System IT Administrator. The Proctor is not a separate eighth role — it is a duty overlay assignable to any existing staff role at administrative discretion. A Guidance Counselor assigned as Proctor temporarily gains the proctoring interface permissions while retaining their base role. The use case diagram shows Proctor as an eighth actor because it is a distinct system interaction, but architecturally it sits on top of the seven-role RBAC structure.

---

### TRAP 14: The "Where Is RBAC in the IPO?" Question [MEDIUM]

**Q:** "Your IPO input mentions campus data and exam scores. But NEXIAM's defining characteristic is role-based access. Where does RBAC appear in the IPO?"

> RBAC is an architectural decision governing the entire Process and Output stages. It is not an input variable because it is not data we collect — it is a design principle we enforce. Within the IPO, RBAC belongs structurally inside Process as part of the RAD development where the authorization matrix is defined, and inside Output as a built-in feature of the delivered platform. The IPO captures research variables and stages; RBAC is the governing architecture applied throughout.

---

### TRAP 15: The "Why IPO? Why Not CIPP?" Question [MEDIUM]

**Q:** "IPO is a systems engineering model. Why use it as a research framework instead of something like CIPP?"

> We chose IPO specifically because of its alignment with the descriptive-developmental design. IPO clearly separates the diagnostic stage (Input — what exists today), the intervention stage (Process — RAD development plus evaluation), and the outcome stage (Output — the platform and empirical results). More complex frameworks like CIPP add context and product evaluation layers that overlap with what Chapter 3 already covers through TAM. IPO keeps the framework focused on the development-evaluation cycle without duplication.

---

### TRAP 16: The "TAM Inside Process — Why?" Question [MEDIUM]

**Q:** "Your IPO shows TAM evaluation inside the Process box. But TAM evaluates the output, not the process. Why not put TAM in Output?"

> TAM serves two roles in our design. The administration of TAM — the pre-test and post-test — is part of the research process that generates data. The results of TAM — the acceptance measures — are part of the Output alongside the platform itself. Process contains the act of measuring; Output contains what the measurement found. This matches our methodological structure where Chapter 3 covers both the evaluation procedure and the results.

---

### TRAP 17: The "K-Means — How Is K Defined?" Question [HIGH]

**Q:** "How exactly does your algorithm define K, and what features are you clustering?"

> K represents predefined program categories aligned with ISPSC's academic offerings — STEM-aligned, Business-aligned, Arts-aligned, and Education-aligned clusters. The features we cluster are multi-dimensional scores from admission exam sub-tests: Math, Logic, Reading Comprehension, and subject-specific sections. The algorithm groups applicants with similar score vectors to highlight their natural academic strengths. The Guidance Counselor reviews these clusters on their dashboard. The algorithm recommends; the counselor decides. This is structured as a Model Adapter architecture — the K-Means engine can be swapped for a different model without changing the rest of the system.

---

### TRAP 18: The "Dangerous AI — Is K-Means Making Admissions Decisions?" Question [CRITICAL]

**Q:** "Is it not dangerous to let an AI system decide which applicants get admitted or rejected?"

> I completely agree, which is why our K-Means module is strictly descriptive, not prescriptive. It does not make admission decisions. It mathematically surfaces aptitude patterns, but the Guidance Counselor always makes the final human call. The system proposes; the authorized officer disposes. Two safeguards enforce this. First, every placement decision requires manual counselor confirmation in the system. Second, the HMAC-SHA256 audit log records who approved each placement and when — creating an accountability trail. Passive acceptance of AI output is structurally prevented.

---

### TRAP 19: The "K-Means — Counselor Override?" Question [ELITE]

**Q:** "You say the counselor makes the final decision. But who ensures the counselor doesn't just blindly follow the K-Means recommendation?"

> That is a valid concern. Two structural safeguards exist. First, the HMAC-SHA256 audit log records who approved each placement and when, creating individual accountability. Second, the K-Means dashboard displays multiple clusters with confidence boundaries — not a single recommended placement — so the counselor must actively interpret the data rather than passively accept a single output. The system is designed so that taking the AI's output without review would require active confirmation.

---

### TRAP 20: The "RBAC — How Does Cross-Role Isolation Actually Work?" Question [MEDIUM]

**Q:** "With seven roles accessing the same system, how do you prevent a Guidance Counselor from accidentally modifying Registrar data?"

> Through database-level RBAC enforced at the API layer. Each role's permissions are defined in the authorization matrix during User Design. Exam scores are immutable records owned by the Registrar role. Guidance Counselor API endpoints lack Update or Delete permissions for registrar-owned tables. The system structurally prevents cross-role data contamination — it is not a UI-level restriction that can be bypassed by calling the API directly. This is documented in the project plan's User Design phase.

---

### TRAP 21: The "HMAC-SHA256 — Is It Real Crypto or Just Logging?" Question [HIGH]

**Q:** "You mention HMAC-SHA256 for audit logging. Is this actual cryptographic chaining, or just system logging with a hash?"

> There is actual cryptographic chaining. Each action event generates an HMAC-SHA256 hash using a server-side secret key. The hash computation includes the previous event's hash as input, forming a chain. If anyone tampers with any log entry, the hash chain breaks — the next entry's hash will not verify against the tampered entry. This makes any unauthorized modification structurally detectable. This is not standard logging; it is a cryptographic audit trail.

---

### TRAP 22: The "Esquivel — How Does Enrollment Prediction Justify Admissions?" Question [MEDIUM]

**Q:** "Esquivel and Esquivel predicted enrollment from admission data. But your system is about admission testing, not enrollment."

> Enrollment predictability proves that program quotas are not static numbers — they are forecasts that affect resource allocation. When admissions are slow, enrollment projections drift. Esquivel demonstrated that enrollment likelihood can be modeled from admission-stage data at a Philippine university. This means Program Heads need visibility into applicant volumes during the admission cycle — not after. That visibility is what the pre-enrollment slate output in our IPO provides.

---

### TRAP 23: The "Ruffalo Noel Levitz Is a Marketing Firm" Question [ELITE]

**Q:** "Ruffalo Noel Levitz is a marketing consultancy, not an academic source. Why cite them?"

> They published an industry report based on actual student behavior data showing that processing speed directly affects applicant yield — students commit to whichever institution processes them faster. It is practitioner evidence, not academic research, and we cite it for exactly what it is: empirical observation from the enrollment management industry. The academic sources — French et al., Sánchez-Mendiola et al., Liu et al. — carry the scholarly weight. Ruffalo Noel Levitz adds the practitioner dimension that speed has measurable operational consequences.

---

### TRAP 24: The "Dash et al. Only Went to Design Stage" Question [MEDIUM]

**Q:** "Dash et al. stopped at design. Why should we believe you'll succeed where they didn't?"

> Dash validated the concept — a unified AI-assisted admission platform is architecturally feasible. They stopped at design, which means the research community has acknowledged the gap but has not shipped. Our advantage is methodology. Dash used a traditional development approach. We use AI-augmented RAD, where AI agents handle routine implementation work and the team focuses on architecture and integration. Concept proven, methodology advanced — that is the difference.

---

### TRAP 25: The "Self-Citation — Too Many ISPSC Studies?" Question [MEDIUM]

**Q:** "Several of your citations — Yukee, Ballesteros, Angala, Cenezan — are from the same institution. Is there a citation circle?"

> The fact that multiple ISPSC studies have independently validated different components — K-Means profiling (Yukee), student clustering (Ballesteros), document management (Angala), records management (Cenezan) — proves that this campus already has the research precedent and technical capability. The problem is not a lack of local knowledge; it is the lack of integration. These are independent capstone studies from different years and different teams. They represent the campus's research output, which NEXIAM seeks to unify into one pipeline.

---

### TRAP 26: The "Peer Review Status of Your Precedents" Question [HIGH]

**Q:** "Dayata and Yap, Tan — were these peer-reviewed? How reputable are the venues?"

> Dayata and Yap published at IEEE COMNETSAT — an IEEE-sponsored international conference on communication networks and satellite communications, peer-reviewed and indexed in IEEE Xplore. Tan published at ACM's International Conference on Intelligent Education and Computer Technology, peer-reviewed and indexed in the ACM Digital Library. Cuerdo published in the International Journal of Theory and Application in Elementary and Secondary School Education, a peer-reviewed journal. Each precedent carries independent peer-review validation.

---

### TRAP 27: The "85%/41% AI Stats — Where in Your Manuscript?" Question [CRITICAL]

**Q:** "Your presentation shows 85% of developers use AI coding agents and 41% of code is AI-generated. Where do these numbers appear in your manuscript?"

> The statistics are from Osmani's 2026 Google whitepaper on agentic engineering, which we cite in our Software Model section. The manuscript describes the paradigm shift qualitatively — the transition from ad-hoc prompting to agentic engineering — citing both Osmani and Peng (2023) on productivity gains. The specific percentages appear on the slide as a visual anchor for the RAD methodology. You are correct that they are not quoted verbatim in the manuscript body. The manuscript focuses on the qualitative justification; the slide provides the quantitative context from the same source.

---

## SECTION 2: JAYPEE — Scope, Development, Deployment

---

### TRAP 28: The "Four Months Is Unrealistic" Question [CRITICAL]

**Q:** "OMR, RAG, K-Means, RBAC, scheduling, portal, audit trails — four months with three people?"

> Four things make this achievable. First, every component uses existing, published implementations — we are integrating proven building blocks, not building from scratch. Second, AI-augmented RAD eliminates the manual syntax bottleneck. Third, our project plan allocates the largest continuous block to Rapid Construction — early July through August, with iterative back-and-forth between design refinement and construction. Fourth, the team has cross-functional dual roles that keep momentum continuous.
>
> We acknowledge the timeline is tight. Scope is prioritized: core functionality over feature breadth. Free-tier AI inference bounds concurrent request volume — another documented limitation. The Gantt chart explicitly accounts for academic course load and weekend coding sessions.

---

### TRAP 29: The "Offline Proctoring + Cloud = Contradiction" Question [CRITICAL]

**Q:** "You say cloud-hosted but proctoring works offline. How do you reconcile those?"

> They serve different operational realities. ISPSC Tagudin has reliable network connectivity in office areas — Registrar, Guidance, Program Head offices. The examination venues may have limited or no reliable connectivity. NEXIAM is cloud-hosted for all functions outside the proctoring interface. The proctoring interface alone uses service-worker caching to maintain exam session state during connectivity loss. When connection restores, cached data synchronizes to the cloud database. This is not a contradiction — it is a design response to a documented infrastructure reality. The manuscript scope section explicitly states this.

---

### TRAP 30: The "What If Service Worker Fails Mid-Exam?" Question [HIGH]

**Q:** "What if the proctor's browser crashes during an offline exam?"

> Service-worker caching persists across browser sessions — a crash does not wipe the cache. The exam session state serializes to cache on every state change. On browser restart, the service worker restores the last saved state. The risk is data loss between the last save and the crash — for a multi-choice exam interface, that means at most one question's response. This is a reliability trade-off inherent to offline-first web architecture. Formal redundancy such as local database replication is future work outside this study's scope.

---

### TRAP 31: The "Cache Poisoning — Student Hacks the Offline Cache?" Question [ELITE]

**Q:** "What stops a student from hacking the offline cache and changing attendance data?"

> The service-worker cache is restricted exclusively to the Proctor's authenticated session — not the applicant's. The cached data is structurally limited to basic session state and attendance markers. When connection restores, the system synchronizes using a secure token payload that validates upload integrity against the backend server. Students never interact with the proctoring interface — they take the exam on paper. The offline cache belongs to the Proctor role and is isolated from all other system components.

---

### TRAP 32: The "Free-Tier AI — What Happens at Peak Load?" Question [MEDIUM]

**Q:** "Your AI components depend on free-tier API access. What happens when rate limit hits during an exam?"

> The chatbot and scheduling assistant are designed with graceful degradation. If the API rate limit is reached, the chatbot displays a fallback message directing the applicant to contact the Guidance Office — the same channel available today. The scheduling assistant switches to a manual proposal interface. Core functions — OMR scoring, RBAC, the applicant portal — are not affected because they do not depend on external API calls. Free-tier dependency is acknowledged as a limitation in the manuscript.

---

### TRAP 33: The "Why RAD Instead of Agile?" Question [HIGH]

**Q:** "Why RAD instead of Scrum or Agile? Isn't RAD outdated?"

> RAD is not outdated — it is specifically validated for Philippine SUC system deployments. Olipas (2020) used RAD for a student information system at a Philippine HEI. Melendres and Aranda (2024) used it for a web-based resident information system. Both studies confirm RAD's functional suitability and security outcomes in our exact deployment context.
>
> The four-phase RAD structure also maps cleanly to our descriptive-developmental research design: Requirements Planning serves the descriptive phase (SO1, SO2); User Design through Cutover serves the developmental and evaluation phases (SO2, SO3, SO4). Agile's continuous iteration would complicate the clear phase-to-objective mapping required for capstone documentation.

---

### TRAP 34: The "AI Writes the Code — What Did You Actually Do?" Question [CRITICAL]

**Q:** "If AI generates most of your code, what is your contribution as developers?"

> AI writes routine implementation — boilerplate, CRUD operations, standard UI components. Our team does five things AI cannot: we define the architecture — RBAC schema, authorization matrix, Model Adapter layer for swappable ML engines. We write specifications grounding AI output in ISPSC's specific operational reality. We conduct rigorous peer code reviews on every AI-generated module — AI writes, humans decide what ships. We build complex integration layers — connecting OMR to the database, wiring RAG to institutional documents, linking K-Means output to the Guidance dashboard. We test, validate, and iterate against staff feedback. The methodology is AI-augmented, not AI-replaced.

---

### TRAP 35: The "Why Cloud? Why Not On-Premises?" Question [MEDIUM]

**Q:** "Your manuscript says cloud hosting due to 'local campus hardware constraints.' What constraints?"

> ISPSC Tagudin Campus does not maintain dedicated server infrastructure for web application hosting at the scale NEXIAM requires. Deploying on-premises would require procuring, configuring, and maintaining hardware — cost and timeline factors outside a capstone project's resources. Cloud hosting on a Dokploy-managed VPS gives us production-grade deployment with containerized isolation at a fraction of the cost. The trade-off — all non-proctoring functions require internet connectivity — is explicitly documented.

---

### TRAP 36: The "September Evaluation — No Real Applicants" Question [HIGH]

**Q:** "Your evaluation happens outside the admission cycle with simulated evaluators. How can you claim the system works for real admissions?"

> We don't claim it works for real admissions — we claim it is acceptable to the people who would operate it during one. The simulated evaluators are the exact same staff who manage the real cycle — Guidance, Registrar, Program Heads. They evaluate NEXIAM against the manual workflow they actually know. First-year students just experienced the real admission process months earlier.
>
> The limitation is acknowledged: results are bounded by simulated operational conditions. Live-applicant validation during an actual admission cycle is future work. What we can establish now is whether the system is acceptable to the institutional stakeholders who would use it.

---

### TRAP 37: The "Team of 3 — How Is Code Review Independent?" Question [MEDIUM]

**Q:** "Your peer code review is done by the same team that wrote the code. How is that independent?"

> The project assignments in Table 1 formalize the separation. QA testing is assigned exclusively to Christine — she does not write production code. When Jaypee's AI-generated modules come in, Christine tests them. When David's architectural components ship, Jaypee and Christine review them. The dual-role structure creates overlap: every module gets eyes from at least two people, and the QA tester is structurally separated from construction. For a three-person academic team, formal QA isolation from construction is the strongest feasible independence structure.

---

### TRAP 38: The "OMR Perspective Correction — How Do You Handle Scanner Skew?" Question [MEDIUM]

**Q:** "What if the scanner feeds paper crooked? A tilt misaligns bubble coordinates."

> You are right — which is why our pipeline does not rely on static pixel coordinates. The OMR script executes a perspective correction algorithm first. It detects four printed anchor corners on our custom answer sheets, warps the skewed image back into a perfect flat rectangle, and only then applies contour detection to read the bubbles. The system mathematically corrects scanner drift before grading. This approach is based on Cuerdo's validated methodology for Philippine public school settings.

---

### TRAP 39: The "Mobile Accessibility for Rural Applicants" Question [MEDIUM]

**Q:** "Many rural applicants do not own laptops. Are you forcing them to use internet cafes?"

> No. NEXIAM's frontend interfaces — especially the Applicant Portal — are built mobile-first with responsive design. The interface scales to smartphones and tablets. As long as the applicant has a basic mobile data connection, they can access their profile, interact with the RAG chatbot, and view results from their phone. This design choice directly addresses the equity gap identified in the Background.

---

### TRAP 40: The "Service Worker Storage Limits" Question [ELITE]

**Q:** "Service-worker caching has storage limits. How many proctor sessions can the offline cache hold?"

> The offline cache is designed for single-session resilience, not bulk offline storage. It holds one active exam session's attendance data and session state. The expectation is that connectivity interruptions are intermittent rather than sustained. If a venue has no connectivity at all, the proctor uploads results once connectivity returns. The service worker is a failsafe for short outages, not an offline mode for prolonged disconnection.

---

## SECTION 3: CHRISTINE — Methodology, TAM, Statistics, Bias

---

### TRAP 41: The "Descriptive-Developmental vs Quasi-Experimental" Question [CRITICAL]

**Q:** "Pre-test/post-test with paired t-test is quasi-experimental. Why label it descriptive-developmental?"

> The research design is classified by what the study primarily does, not by one statistical test in one phase. Our study does three things in sequence. One: describe the existing admission process and pinpoint gaps — that is descriptive, addressed by SO1 and SO2. Two: develop NEXIAM to address those gaps — that is developmental, addressed by the RAD construction phase. Three: evaluate whether the system improves upon the manual baseline — the paired TAM. The pre-test/post-test is an evaluation tool within the developmental phase, not an experimental manipulation. We are not randomly assigning groups, manipulating an independent variable, or controlling extraneous variables — which define a true experiment. This classification follows Frey (2022).

---

### TRAP 42: The "Why No Control Group?" Question [CRITICAL]

**Q:** "Why not split evaluators into control and experimental groups for stronger evidence?"

> Structurally impossible at this site. The staff complement is too small to split — Guidance has two personnel, Registrar has three. Splitting gives n=1-2 per group, no statistical power. The system replaces the workflow for the entire office — you cannot have half the staff on paper and half on NEXIAM processing the same applicants. The within-subjects paired design is the correct tool: each evaluator serves as their own control. We measure the same person's perception of the manual process and NEXIAM on the same constructs. That delta answers the research question directly.

---

### TRAP 43: The "Apples to Oranges" Question [CRITICAL]

**Q:** "Pre-test measures manual process, post-test measures NEXIAM. You're comparing two different things. How is that valid?"

> That is precisely the point of SO4. NEXIAM is designed to replace the manual process. The only meaningful comparison is between the old method and the new — experienced by the same type of user performing the same type of task. Both instruments measure the same four TAM constructs using the same 7-point Likert scale. The referent changes — manual process versus NEXIAM — but the measurement framework is identical. A single post-test would tell us absolute acceptance. The paired design tells us relative improvement, which is what SO4 requires.

---

### TRAP 44: The "TAM Pre-Test Breaks Davis Protocol" Question [HIGH]

**Q:** "Davis designed TAM for post-use measurement only. Administering it pre-use breaks the validated protocol."

> The pre-test does not measure pre-adoption perception of NEXIAM — that would indeed break protocol. The pre-test measures evaluator perception of the existing manual admission process across the same four TAM constructs. The post-test then measures NEXIAM on the same constructs. The paired delta captures the shift from manual to automated. A single post-test would only tell absolute acceptance — not whether NEXIAM is significantly better than what they were using before. The 7-point Likert and within-subjects design are well-established for comparative evaluation in IS research.

---

### TRAP 45: The "n=46 Is Too Small" Question [HIGH]

**Q:** "n=46 is below the conventional 30-per-group threshold for parametric tests. Are you underpowered?"

> The 30-per-group threshold applies to independent samples designs, not paired designs. In a paired design, the effective sample is the number of pairs — n=46 paired observations. The Shapiro-Wilk test gates the normality assumption: if paired differences are normally distributed, the paired t-test is valid. We also report effect size — Cohen's d or rank-biserial correlation — not just p-values. Effect size tells how large the improvement is, not just whether it is statistically significant. If normality is violated, Wilcoxon signed-rank is the non-parametric fallback. The data decides the statistical path. This two-gate approach — normality check first, then parametric or non-parametric — is the correct procedure for small-to-moderate paired samples.

---

### TRAP 46: The "Purposive Sampling Bias" Question [HIGH]

**Q:** "Your evaluators were hand-picked. How do you control for selection bias inflating TAM scores?"

> Purposive sampling is not convenience sampling. We did not pick evaluators who would like NEXIAM — we selected for direct operational relevance. The Guidance and Registrar staff are the complete available complement of those offices. The 11 Program Heads represent all academic departments. The 30 first-year students have fresh institutional memory.
>
> The pre-test establishes each evaluator's baseline perception of the manual process. If an evaluator is a harsh rater, they rate both manual and NEXIAM harshly. The paired delta accounts for individual response tendencies. This is precisely why the within-subjects design is stronger than a post-test-only between-groups design for this evaluation context.

---

### TRAP 47: The "Why 7-Point Likert vs 5?" Question [MEDIUM]

**Q:** "Why a 7-point Likert scale instead of the more common 5-point?"

> A 7-point scale provides greater variance for detecting the pre-to-post perception shift that SO4 measures. With 5 points, respondents tend to cluster around the midpoint, reducing the detectable effect size. Seven points give finer granularity. This is particularly important when measuring a within-subjects shift where the expected improvement may be moderate rather than dramatic. Davis (1989) originally used a 7-point Likert scale for TAM — we follow the instrument's validated specification.

---

### TRAP 48: The "16-Item TAM — But 4 Roles × 4 Items × 4 Constructs = 64 Unique Phrasings" Question [HIGH]

**Q:** "You say 16-item TAM, but your tables show 64 unique item phrasings. Which is it?"

> The TAM instrument has 16 items — 4 constructs × 4 items each. Those 16 items are role-adapted: the same construct is measured across all four respondent groups, but phrasing is tailored to each group's operational reality. A Registrar sees "NEXIAM improves my ability to track applicant records." A Guidance counselor sees "NEXIAM improves my ability to evaluate applicant profiles." Both measure Perceived Usefulness — the same TAM item, adapted for context. The psychometric properties are preserved because the underlying construct is identical. We report it as a 16-item instrument because the adaptation is phrasing-level, not construct-level.

---

### TRAP 49: The "IT Expert — Why Separate from TAM?" Question [MEDIUM]

**Q:** "Why is the IT expert excluded from TAM?"

> TAM measures end-user acceptance — perceived usefulness, ease of use, attitude, behavioral intention. An IT expert evaluating system architecture, security posture, and maintainability is not an end user in the TAM sense. The constructs do not fit their evaluation criteria.
>
> Instead, the IT expert conducts a structured technical walkthrough covering architecture soundness, security posture, data privacy compliance, and maintainability. Findings are reported qualitatively alongside TAM results as supplementary expert validation. Because n=1, no inferential statistics are applied. This separation is documented in Research Instruments and Data Analysis.

---

### TRAP 50: The "Shapiro-Wilk on n=46 — Won't It Always Pass?" Question [ELITE]

**Q:** "Shapiro-Wilk on small samples has low power to reject non-normality. What if the data is actually non-normal and the test just missed it?"

> This is a valid concern. At n=46, Shapiro-Wilk has moderate power — it detects gross departures but may miss subtle violations. Two safeguards. First, we report the Shapiro-Wilk statistic and p-value explicitly so the panel can assess the evidence. Second, we also report effect size — Cohen's d or rank-biserial correlation — which is robust to distributional assumptions and provides meaningful practical significance regardless of which test path is taken. If the panel prefers, we can default to Wilcoxon as the primary test and report t-test as supplementary — because Wilcoxon makes no distributional assumptions.

---

### TRAP 51: The "Effect Size — Why Important?" Question [MEDIUM]

**Q:** "Why report effect size in addition to p-values?"

> A significant p-value tells you a difference exists. Effect size tells you how big that difference is — whether the improvement is practically meaningful or merely statistically detectable. A paired t-test with n=46 could produce a significant p-value for a trivially small improvement. Cohen's d or the rank-biserial correlation quantifies the magnitude. This is standard APA-recommended reporting practice.

---

### TRAP 52: The "Thematic Analysis — Who Validates the Themes?" Question [HIGH]

**Q:** "SO1 uses thematic analysis on interviews. How do you ensure the themes are valid and not just what you wanted to find?"

> Themes are validated against two independent evidence sources. Field observation notes supply procedural context that questionnaire data alone cannot capture. If a staff member claims scoring takes five days but field observation documents a two-week cycle at peak, the theme captures the range. Second, extracted themes are brought back to the Guidance and Registrar offices for confirmation: "This is what we heard. Is this accurate?" Only confirmed themes become functional requirements. The project plan enforces this — Requirements Planning concludes only after findings are confirmed directly with the offices.

---

### TRAP 53: The "TAM Means Above 4.0 — Is That Really Acceptance?" Question [HIGH]

**Q:** "You define acceptance as pooled TAM mean above 4.0 on a 7-point scale. But 4.0 is barely above neutral."

> The 4.0 threshold is the scale midpoint — it separates positive from negative perception. It is the standard interpretive benchmark in TAM literature, not a bar we invented. We report the exact means and standard deviations per construct per role so the panel can assess the strength of acceptance directly. If a construct mean is 4.2 with wide standard deviation, that is weak acceptance with high variability. If it is 6.1 with narrow deviation, that is strong, consistent acceptance. The numbers are reported transparently. The paired t-test in SO4 provides the complementary answer: regardless of absolute means, is NEXIAM significantly higher than the manual baseline?

---

### TRAP 54: The "Why Only PU and PEOU in SO4?" Question [MEDIUM]

**Q:** "Your null hypotheses only test Perceived Usefulness and Perceived Ease of Use. Why not Attitude and Behavioral Intention?"

> PU and PEOU are the two core constructs of the original TAM. Davis (1989) established them as the primary determinants of technology acceptance. ATU and BIU are downstream constructs that mediate the PU/PEOU-to-usage relationship. Testing all four would inflate the familywise error rate with four separate paired tests without clear methodological benefit. If PU and PEOU both show significant improvement, the downstream constructs logically follow — the TAM literature has established that causal chain through decades of validation.

---

### TRAP 55: The "First-Year Students Aren't Real Applicants" Question [HIGH]

**Q:** "Your 'Applicant' group is first-year students who already got in. Doesn't this invalidate their evaluations?"

> They just completed the real admission process months earlier — the experience is fresh and authentic. They know what the manual process felt like: the trips to campus, the Facebook page monitoring, the waiting. That direct experience is actually more valuable than a hypothetical applicant who has never gone through any admission process.
>
> The limitation is acknowledged: results are bounded by simulated operational conditions. A first-year student evaluating NEXIAM's portal in September is not identical to a real applicant during a live cycle. But for measuring the shift from a known manual experience to a new digital one, first-year students with fresh institutional memory are the strongest available group.

---

### TRAP 56: The "Program Heads — What Do They Actually Evaluate?" Question [MEDIUM]

**Q:** "You have 11 Program Heads. What exactly do they evaluate? They don't process applications."

> Program Heads evaluate two specific NEXIAM outputs: pre-enrollment slates and the decision-support triage. In the manual process, they receive informal text messages about incoming applicant volumes — unstructured, delayed, incomplete. NEXIAM's pre-enrollment slate gives them a structured dashboard showing how many applicants target each program, with K-Means surfaced aptitude patterns. They also evaluate the decision-support triage because they set local program qualification standards. When an applicant's aptitude pattern suggests a mismatch with their first-choice program, the Program Head sees the triage note alongside the counselor's placement recommendation.

---

### TRAP 57: The "Hawthorne Effect / Observation Bias" Question [HIGH]

**Q:** "Evaluators know they are in a study. Won't they just rate highly to be nice?"

> You are referring to the Hawthorne effect, which we mitigate in two ways. First, the TAM evaluation is strictly anonymized — no identifying information is collected on the questionnaire. Second, pre-simulation instructions emphasize that we are testing the software's flaws, not the evaluator's performance. By framing the exercise as a critical stress-test rather than a showcase, we encourage honest responses rather than artificial praise.

---

### TRAP 58: The "Why TAM and Not SUS or ISO 25010?" Question [HIGH]

**Q:** "There are many evaluation models. Why TAM specifically, and not SUS or ISO 25010?"

> Our primary hurdle at ISPSC Tagudin is not just usability but institutional adoption. A system can be technically perfect — which ISO 25010 measures — but if Registrar or Guidance staff perceive it as extra work, they will revert to paper. TAM specifically measures Perceived Usefulness and Behavioral Intention to Use, which directly answers whether staff will actually adopt NEXIAM in the real world. TAM is the standard model for predicting technology adoption, validated across hundreds of studies since Davis (1989). SUS measures usability, which is a subset of what we need. ISO 25010 measures software quality, which the IT expert already covers through the technical walkthrough.

---

### TRAP 59: The "TAM Pre-Test Ceiling Effect" Question [ELITE]

**Q:** "What if your pre-test already shows high scores? If staff already rate the manual process positively, you may not detect any improvement."

> A ceiling effect would actually be informative. If evaluators already rate the manual process highly on perceived usefulness, that finding would indicate the operational problem is not perceived as severe by staff — a meaningful result in itself. However, our background data — 5-to-14-day scoring delays, Facebook page result posting, text-message coordination — suggests low pre-test scores are more likely. We acknowledge ceiling effects as a risk and will report any observed limitations transparently in Chapter 3.

---

### TRAP 60: The "TAM Without Real Integration — Testing Individual Features vs Integration" Question [ELITE]

**Q:** "Your TAM items probe individual features. How do you measure whether the integration itself adds value?"

> The TAM questionnaire is administered after evaluators use the complete NEXIAM system, not individual features in isolation. When a Registrar uses the system, they experience the full pipeline: applicant registration, status visibility, inter-office coordination — all under RBAC. When a Guidance counselor uses it, they experience scheduling, OMR scoring, triage, and profiling. Their perception of usefulness reflects the integrated experience, not any single module. The integration is inherently evaluated because the system they test IS the integrated system. The shared TAM items — inter-office coordination (PU-4), visibility (PU-3) — specifically probe the integration value.

---

## SECTION 4: WEAK POINTS — Acknowledge, Don't Defend

When asked about these, name the section, acknowledge, move on. Do NOT defend.

1. "Simulated evaluators limit external validity" → Scope: "results bounded by simulated operational conditions"
2. "No formal NPC certification" → Scope: "outside this study's scope"
3. "Free-tier AI inference limits concurrent requests" → Scope: "paid capacity outside scope"
4. "Osmani whitepaper on Kaggle, not peer-reviewed" → Acknowledge as practitioner evidence. Peng (2023) carries peer-reviewed productivity evidence
5. "n=46 for t-test, n=1 for IT expert" → Data Analysis: "sample size is relatively small"
6. "September evaluation outside admission cycle" → Scope: acknowledged limitation
7. "Woelert duplicate in reference list" → Acknowledge formatting error, will correct
8. "Dash et al. in Istoriya Journal — verify quality" → Pivot to stronger precedents (Cuerdo, Dayata, Tan)

---

## SECTION 5: CROSS-DOMAIN PASS-OFF PHRASES

- **David → Christine:** "For the statistical methodology behind that claim, I defer to Christine, our QA Tester and Data Analyst."
- **Christine → Jaypee:** "For the technical architecture of that component, I defer to Jaypee, our Lead Developer."
- **Jaypee → David:** "For the legal and policy framework, I defer to David, our Project Manager."
- **Anyone → Adviser:** "This was reviewed and approved under our adviser, Dr. Zeus Vincent Mendoza."

---

## SECTION 6: TEAM DRILL PATTERN

**Phase 1: Individual (30 min each)** — Read your section aloud × 3. Answers should feel natural.

**Phase 2: Pair Drill (20 min per pair)** — Fire random traps. Answer within 30 seconds. Swap.

**Phase 3: Full Run (30 min)** — Panelist player fires traps randomly. Practice pass-offs. Time full Q&A.

**CRITICAL FIRST 5 MINUTES — memorize these three answers:**

1. "Why so many features?" → David (Trap 2) — 3-part: feature-to-failure, integration contribution, bounded scope
2. "This is quasi-experimental, not descriptive-developmental" → Christine (Trap 41) — primary activity classification, evaluation tool vs manipulation
3. "Why no control group?" → Christine (Trap 42) — staff too few, system replaces workflow, within-subjects is correct

---

*Generated from NEXIAM manuscript (July 2026), speaker script (v4), presentation slides, and legacy Q&A bank (v4). 60 traps covering Background, Architecture, Scope, Development, Methodology, Statistics, and Policy. Document alignment fixes applied to script and presentation text.*
