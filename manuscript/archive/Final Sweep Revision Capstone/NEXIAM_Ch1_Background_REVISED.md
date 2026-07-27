# NEXIAM — Chapter 1: Background of the Study
### Revised Draft | Research-Grade | No em dashes | Terminology locked
### Esquivel & Esquivel (2020) restored per instruction

---

> **HOW TO USE THIS FILE**
> Paste this text directly into your Word document, replacing everything
> from the current "Background of the Study" heading through the final
> NEXIAM closing paragraph (end of p. 10 in the current manuscript).
> Do not replace anything from "Conceptual Framework of the Study" onward.
> The [REMOVED TO CHAPTER 3] block at the bottom is your migration queue
> for SO1; keep it in this file until those passages are confirmed placed
> in Chapter 3.

---

## Background of the Study

Education and examination are inseparable. From the earliest formal
institutions, testing has served two functions simultaneously: it
measures what a learner has acquired, and it filters who advances.
High-stakes assessments still dominate summative evaluation in higher
education, yet a 2024 scoping review by French et al. found that their
pedagogical benefits, including stronger knowledge retention and
motivation for some learners, are frequently outweighed by drawbacks
such as heightened student anxiety, surface-level learning, and equity
concerns. Even as assessment design draws this level of scrutiny, the
operational logistics of running examinations at scale remain
comparatively under-researched relative to their institutional impact
(Woelert, 2023).

Where researchers have examined that administrative infrastructure
directly, they document persistent bottlenecks that strain the integrity
of the selection process itself. Patil et al. (2021) found that
large-scale exam management produces acute challenges in record
integrity, applicant verification, and resource coordination, problems
that intensify in paper-based environments. Thakur et al. (2025) extend
this point through their TrueGradeAI framework, showing that physical
exam workflows generate severe grading delays and leave room for
subjective bias, while arguing that digitization can compress
turnaround from weeks to hours and improve transparency.
Sanchez-Mendiola et al. (2025), analyzing results from more than
600,000 examinees sitting a single university's admission exam across
the pandemic, illustrate just how much operational scale a high-stakes
testing program must sustain even under serious disruption.

The pattern that emerges is a structural mismatch: institutions invest
in psychometrically sound assessment instruments but lack the downstream
infrastructure to administer, score, and release results efficiently.
The cost of that mismatch is not abstract. Liu et al. (2025) show that
conventional admission-decision processes still rely on fixed historical
yield assumptions and largely ignore the statistical uncertainty in
applicant behavior, leaving institutions exposed to over- or
under-enrollment even before logistics enter the picture. Once results
are released, the speed of that release shapes enrollment behavior
directly: applicants weighing more than one offer tend to commit to
whichever institution delivers a clear outcome first (Ruffalo Noel
Levitz, 2023). Slow processing in this context does more than
inconvenience applicants; it undermines the gatekeeping function the
examination was designed to perform.

Internationally, higher education institutions have responded by
adopting artificial intelligence and cloud platforms to modernize these
administrative services (EDUCAUSE, 2023), a shift accelerated further
by the infrastructure pressures the COVID-19 pandemic placed on
long-deferred digitalization plans (Marinoni et al., 2020). AI-powered
chatbots now handle routine applicant inquiries at scale, extending
staff coverage without requiring one-on-one engagement for every
question (Labadze et al., 2023), while role-based access control has
become the established security architecture for educational data
systems, restricting users to only the data their organizational
function requires (Sandhu et al., 1996; Kabier et al., 2023).

Within Philippine higher education specifically, digital adoption is
driven less by market pressure than by policy mandate. The CHED
strategic plan (CHED, 2022) sets the institutional direction, while
Republic Act No. 10931 (2017) exponentially increased admission volumes
nationwide by removing public tuition fees, straining staff and
infrastructure that did not scale with it. The yield uncertainty Liu et
al. (2025) document globally is not a foreign abstraction here either:
Esquivel and Esquivel (2020), modeling enrollment likelihood at a
Philippine university with logistic regression, found that the K-12
curriculum transition had already destabilized freshman enrollment
projections enough to push institutions toward predictive modeling just
to estimate incoming class sizes. Concurrently, Republic Act No. 10173
(2012) imposes data governance obligations that paper-based systems
structurally cannot support, an obligation the National Privacy
Commission (2020) has since operationalized for the education sector
through advisories specifically governing the handling of applicant
records, while Republic Act No. 10844 (2016) established the
Department of Information and Communications Technology as the national
lead agency for ICT policy, planning, and coordination, a mandate that
has since driven digitalization initiatives across state agencies. That
mandate is racing against a readiness gap at the state-college level:
Villarino (2025), surveying 451 students at a rural state college in
Cebu, found that student adoption of AI tools had already outpaced any
institutional framework governing their use, with most respondents
using tools such as ChatGPT well ahead of campus policy, a finding that
pushed the study to question whether the assumed rural digital divide
still holds.

ISPSC Tagudin Campus is where these national pressures converge on an
institution processing 700 to 1,000 freshman applicants annually through
a manual workflow spanning intake, examination, and result release.
Institutional research already exists within ISPSC Tagudin Campus itself
that speaks directly to part of the admission problem, particularly
applicant profiling for course routing. Yukee et al. (2025) validated a
K-Means applicant-profiling configuration on the campus's own entrance
examination data, and Ballesteros et al. (2025) clustered first-year
student profiles using unsupervised machine learning, with family income
and Grade 12 General Weighted Average as primary clustering features
alongside categorical descriptors such as municipality of origin, Senior
High School type, and academic track. Both validated their respective
configurations for specific, prescribed evaluation use cases rather than
as continuously running tools integrated into the live admission
workflow, and neither is connected to the inter-office coordination that
shapes how counselors interact with applicants. Ballesteros et al.
(2025) further documented that the campus's existing enrollment system
captures only basic applicant demographics, specifically program,
municipality, and sex, at the point of registration, leaving Grade 12
General Weighted Average and broader socioeconomic indicators largely
uncaptured at intake despite their demonstrated relevance to
course-routing analysis.

The campus has likewise begun digitizing other administrative functions
in isolation: Angala et al. (2023) implemented a document management
system for ISPSC Tagudin Campus to address the same paper-accumulation
problem the current admission workflow presents, while Cenezan and
Gacusan (2025) and Cenezan and Valdez (2023) developed analytics-driven
records-management and performance-evaluation systems, respectively, at
the Main Campus. Each was evaluated and favorably received, yet none has
reached systemwide deployment; Cenezan and Gacusan's own evaluation
explicitly recommends, rather than reports, institution-wide rollout.
The campus, in other words, does not lack algorithmic precedent, local
data, or a track record of successful systems digitization. What it
lacks is the admission testing system as a whole: a single,
role-governed, campus-controlled architecture in which scoring, aptitude
classification, and structured guidance delivery operate as integrated
parts of one pipeline, rather than as separate features attached to
separate offices. None of the isolated systems above, individually or
pieced together, constitutes that whole, and none touches the admission
testing phase at all.

Automating these functions must be reconciled with the data governance
obligation established above. Republic Act No. 10173 permits the
outsourcing of data processing to external services under its Principle
of Accountability (Sec. 21), provided the controller remains fully
responsible for the processor's handling of the data and implements
security safeguards equivalent to those required for direct processing.
Any proposed digital solution would need to satisfy that same standard
through application-layer controls that logically isolate protected
records before any external processing service is engaged (Slade and
Prinsloo, 2013). For the advisory layer specifically, Retrieval-Augmented
Generation offers a deployable answer: rather than composing responses
from a general-purpose model's own parameters, a RAG-based chatbot
retrieves only from a curated local knowledge base before generating an
answer, constraining output to what institutional policy documents
actually say (Lewis et al., 2020).

NEXIAM, a Role-Based College Admission Testing System for the Guidance
and Registrar Offices at ISPSC Tagudin Campus, is the proposed response
to this gap. Whether a single governed pipeline, built on the confirmed
operational failures of the current process, can outperform the process
it replaces, and whether the offices running it daily find it usable
enough to sustain, is the question this study sets out to answer.

---

## [REMOVED FROM BACKGROUND -- MIGRATION QUEUE FOR CHAPTER 3, SO1]

> These four blocks were removed from the Background because they contain
> primary-data findings gathered through the researchers' own field
> inquiry. They are SO1 results, not background context. Paste them into
> Chapter 3 under the SO1 results subsection. Do not delete this block
> until those passages are confirmed placed.

---

**Block SO1-A: Core operational failures confirmed through field inquiry**
*(was pp. 5-6, "Application forms are submitted physically...")*

Application forms are submitted physically and stored in locked cabinets,
never digitized, so the data they contain becomes inert the moment a
cycle closes. Inter-office scheduling runs on unstructured text messages,
a channel ill-suited to tracking fluctuating department quotas and
counselor availability. Examination sheets are scored by hand using
stencils, a process both Guidance Office respondents independently
confirmed consumes five or more days per cycle, producing cumulative
release delays of up to two weeks, and beyond a month for applicants who
sat the earliest batches (S. Jalorina, personal communication,
June 2026).

---

**Block SO1-B: Result notification pattern and repeated campus-visit burden**
*(was pp. 5-6, "Results are never pushed to applicants directly...")*

Results are never pushed to applicants directly. Applicants must instead
monitor a general-purpose Facebook page that Guidance staff manage only
during office hours, then physically attend an announced retrieval
session to collect results in person. Because the page goes unanswered
outside office hours and on-duty staff are frequently occupied with
other walk-in concerns during the day, confusion over delayed or unclear
posts often pushes applicants to visit the campus directly just to
confirm their status, on top of the retrieval visit itself, a pattern
that can stretch into a third campus trip for a process most of them do
not fully understand until they are already inside it.

---

**Block SO1-C: Counseling-session visibility gap**
*(was p. 6, "During counseling, guidance staff have no independent visibility...")*

During counseling, guidance staff have no independent visibility into
program enrollment figures, advising applicants on quota numbers that
Program Heads communicate informally and that may already be stale by
the time a session takes place. This gap was confirmed through the same
preliminary field observation underlying the findings above.

---

**Block SO1-D: Scheduling and admission-configuration gaps**
*(was p. 6, "Scheduling coordination currently depends on informal negotiation...")*

Scheduling coordination currently depends on informal negotiation among
counselors, Registrar staff, and Program Heads, with no shared
visibility into quota status. Core admission configuration, meanwhile,
exists nowhere as a managed record: it is set up from scratch each cycle
through staff memory and informal coordination rather than through any
digital management interface, leaving no persistent record to carry
forward or audit. Neither lends itself to full automation in the way
scoring or inquiry triage do, since both still require a human
decision-maker to retain final say over coordination outcomes and
configuration changes.
