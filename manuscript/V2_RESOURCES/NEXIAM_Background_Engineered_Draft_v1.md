# NEXIAM Background of the Study — Engineered Draft v1

> **Strategy**: Select only the strongest references. Cut the weak ones. Create a clear funnel: Global Problem → International Solutions → Philippine Context → ISPSC Local Evidence → The Gap NEXIAM Fills.
> **Style**: Academic register, no em dashes, no AI-isms. Short sentences mixed with longer ones. Every claim cites a specific source.

---

**Education and examination are inseparable.** From the earliest formal institutions, testing has served two functions at once. It measures what a learner has acquired, and it filters who advances. High-stakes assessments still dominate summative evaluation in higher education. Yet a 2024 scoping review by French et al. found that their pedagogical benefits, including stronger knowledge retention and motivation for some learners, are frequently outweighed by drawbacks such as heightened student anxiety, surface-level learning, and equity concerns. Even as assessment design draws this level of scrutiny, the operational logistics of running examinations at scale remain comparatively under-researched relative to their institutional impact (Woelert, 2023).

**The bottleneck is not the test. It is the infrastructure around it.** Patil et al. (2021) found that large-scale exam management produces acute challenges in record integrity, applicant verification, and resource coordination. These problems intensify in paper-based environments. Thakur et al. (2025) reinforced this through their TrueGradeAI framework, arguing that digital pipelines do more than automate scoring. They introduce architectural safeguards that minimize human subjectivity and grading bias. The result is a structural mismatch. Institutions invest in psychometrically sound assessment instruments but lack the downstream infrastructure to administer, score, and release results efficiently. The cost of that mismatch is not abstract. Liu et al. (2025) showed that conventional admission processes still rely on fixed historical yield assumptions and ignore the statistical uncertainty in applicant behavior. Prolonged decision timelines distort enrollment projections. Once results are released, speed matters: applicants weighing multiple offers tend to commit to whichever institution delivers a clear outcome first (Ruffalo Noel Levitz, 2023). Slow processing in this context does more than inconvenience applicants. It undermines the gatekeeping function the examination was designed to perform.

**Globally, institutions have begun responding.** Adoption of artificial intelligence and cloud platforms to modernize administrative services has accelerated since the COVID-19 pandemic (EDUCAUSE, 2023; Marinoni et al., 2020). The specific application of AI to admission processes themselves has gained traction as well. Lira et al. (2023), publishing in Science Advances, demonstrated that machine learning models can assess applicant qualities from unstructured application materials, with assessments correlating to long-term life outcomes. This validated the AI-in-admissions space at the highest level of peer review. At the same time, Van Busum and Fang (2025) systematically analyzed bias in AI admission models, raising fairness concerns that any algorithm-assisted system must address. These two findings together motivate the human-in-the-loop architecture that keeps counselor judgment at the center of placement decisions.

**Unified admission platforms have also been tested at scale.** Adwan et al. (2023) developed a centralized mobile-based admission application for multiple universities in the GCC region. Their empirical findings showed 97.7% usability using Nielsen heuristics, proving that the unified platform model works across institutions. AI-powered chatbots now handle routine applicant inquiries at scale (Labadze et al., 2023), while role-based access control has become the established security architecture for educational data systems (Sandhu et al., 1996; Kabier et al., 2023).

**In the Philippines, digital adoption is driven less by market pressure than by policy mandate.** The CHED strategic plan (CHED, 2022) sets institutional direction. Republic Act No. 10931 (2017) increased admission volumes nationwide by removing public tuition fees, straining staff and infrastructure that did not scale with it. Republic Act No. 10173 (2012) imposes data governance obligations that paper-based systems structurally cannot support. The National Privacy Commission (2020) has since operationalized this for the education sector through advisories on handling applicant records. These mandates are racing against a readiness gap at the state college level. Villarino (2025) found that students in provincial state colleges adopt consumer AI tools ahead of institutional frameworks, creating a data governance vacuum that threatens compliance with Republic Act No. 10173. Modern state college software cannot simply remain a passive record-keeping database. It must act as a governance layer that channels existing digital literacy into secure, application-bounded environments.

**Within this context, individual NEXIAM components have already been validated in Philippine settings.** Cuerdo et al. (2021) demonstrated that EvalBee, a freely available OMR tool, significantly reduced scoring time and improved accuracy over manual marking in a Philippine public school. Dayata and Yap (2024) deployed a full retrieval-augmented generation pipeline at the University of San Carlos that answers policy questions from a curated institutional knowledge base, published at IEEE COMNETSAT. Tan (2025) independently validated K-Means optimization for student course selection at an ACM conference. These studies confirm that OMR, RAG chatbots, and K-Means clustering each work in Philippine higher education contexts. What does not exist is a single system that integrates all three under one role-based governance framework.

**ISPSC Tagudin Campus is where these national pressures converge on a specific institution.** The campus processes 700 to 1,000 freshman applicants annually through a fully manual workflow spanning intake, examination, and result release. Institutional research already exists on campus that speaks to parts of the problem. Yukee et al. (2025) validated a K-Means applicant profiling configuration on the campus's own entrance examination data. Ballesteros et al. (2025) clustered first-year student profiles using unsupervised machine learning, with family income and Grade 12 General Weighted Average as primary features. Eustaquio and Nisperos (2023) developed a web-based registration and information system for ISPSC with decision-support capability for billing, grading, and retention tracking. Angala et al. (2023) implemented a document management system, and Cenezan and Gacusan (2025) built analytics-driven records management. Each was evaluated and favorably received. Yet none reached systemwide deployment. None touches the admission testing phase at all.

**The campus does not lack algorithmic precedent, local data, or a track record of successful digitization.** What it lacks is the admission testing system as a whole. A single, role-governed, campus-controlled architecture in which scoring, aptitude classification, and structured guidance delivery operate as integrated parts of one pipeline. That integration must be reconciled with Republic Act No. 10173, which requires application-layer controls that logically isolate protected records before any external processing service is engaged (Slade and Prinsloo, 2013). For the advisory layer specifically, retrieval-augmented generation constrains output to what institutional policy documents actually say (Lewis et al., 2020), rather than composing responses from a general-purpose model's own parameters.

**NEXIAM is the proposed response to this gap.** A role-based college admission testing system for the ISPSC Tagudin Campus Guidance and Registrar Offices. It synchronizes automated scoring, predictive applicant profiling, and governed AI advisory services into a single ecosystem. Whether this integrated pipeline can outperform the manual system it replaces, and whether the offices running it daily find it usable enough to sustain, is the question this study sets out to answer.

---

## Reference Selection Strategy

| Para | Added (from 16 new) | Kept (from existing) | Removed | Why removed |
|---|---|---|---|---|
| 1 | — | French (2024), Woelert (2023) | — | — |
| 2 | — | Patil (2021), Thakur (2025), Liu (2025), Ruffalo Noel Levitz (2023) | Sanchez-Mendiola (2025) | Impressive scale but narrative overhead; Q&A only |
| 3 | **Lira (2023)** Science Advances, **Van Busum & Fang (2025)** | EDUCAUSE (2023), Marinoni (2020) | — | — |
| 4 | **Adwan (2023)** GCC unified | Labadze (2023), Sandhu (1996), Kabier (2023) | — | — |
| 5 | **Cuerdo (2021)** EvalBee PH | CHED (2022), RA 10931, RA 10173, NPC (2020), RA 10844 (shortened), Villarino (2025) | Esquivel & Esquivel (2020) | Weak source; panel will question it |
| 6 | **Dayata & Yap (2024)** USC RAG, **Tan (2025)** K-Means ACM | Yukee (2025), Ballesteros (2025), Eustaquio (2023), Angala (2023), Cenezan | — | — |
| 7 | — | Lewis (2020), Slade & Prinsloo (2013) | — | — |

### Not included in Background (saved for Q&A or other chapters)
- Rutvik et al. (2024) — basic web admission, weaker source
- Dash et al. (2024) — unverified preprint
- Nafrees & Kariapper (2020) — Journal of Critical Reviews (questionable)
- Tamascelli et al. (2025) — overlaps with Dayata/Neupane
- Neupane et al. (2024) — overlaps with Dayata/Ilagan
- Calfoforo & Raga (2024) — overlaps with Dayata
- Ilagan et al. (2025) — good but Dayata is stronger for PH context
- Catalan (2017) — overlaps with Cuerdo; Cuerdo is stronger
- Van Deventer et al. (2024) — arXiv, contrast approach
- Mittal & Malhotra (2026) — survey, better for lit review
