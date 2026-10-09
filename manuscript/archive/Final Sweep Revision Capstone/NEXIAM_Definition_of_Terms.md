# NEXIAM — Chapter 1: Definition of Terms
### New section | Insert after Significance of the Study, before Chapter 2 | No em dashes

---

> **HOW TO USE THIS FILE**
> The institutional guide lists Definition of Terms as a required Chapter 1
> component and it is currently missing from the manuscript entirely (no
> matches found anywhere in the submitted PDF). Insert this section after
> the Significance of the Study and before the start of Chapter 2.
> Alphabetical order, operational (not dictionary) definitions, each tied
> to how the term is actually used in this study.

---

## Definition of Terms

**Admission Configuration.** The set of academic year parameters,
examination room assignments, course quotas, and aptitude area
definitions that each admission cycle depends on, maintained as a
structured, persistent digital record under NEXIAM rather than recreated
from staff memory each cycle.

**AI-augmented Rapid Application Development (RAD).** The four-phase
software development model used to build NEXIAM, consisting of
Requirements Planning, User Design, Rapid Construction, and Cutover, in
which AI-generated code passes through a mandatory human Review and
Integrate step before acceptance.

**Applicant Status Tracking.** The persistent digital record of an
individual applicant's progress through the admission pipeline, from
submission through scoring to result release, viewable in real time by
the applicant and by authorized staff according to their role.

**Behavioral Intention to Use (BIU).** A Technology Acceptance Model
construct measuring the extent to which a respondent intends to use
NEXIAM going forward, distinct from whether they currently find it
useful or easy to use.

**Decision-Support Reference.** The operational role of the K-Means
Aptitude Insights Dashboard in this study: a reference the Guidance
Counselor actively factors into placement judgment. The dashboard
surfaces a data pattern; it does not make or recommend a placement
decision on the counselor's behalf.

**Human-Gated AI Scheduling Assistant.** The NEXIAM module that generates
proposed examination slot and proctor assignments. No schedule is
finalized without explicit review and approval by authorized staff; the
system proposes, a person approves.

**HMAC-SHA256 Audit Logging.** A cryptographic signing method used to
record every state-modifying action in NEXIAM (score submissions, role
assignments, schedule approvals, status changes) in a tamper-evident log,
so that any alteration after the fact is detectable.

**Hybrid RAG Chatbot.** A chatbot built on Retrieval-Augmented Generation,
which answers applicant inquiries by retrieving only from a curated local
knowledge base of institutional policy documents before generating a
response, rather than drawing on a general-purpose model's own training
data.

**ICAT (Institutional College Admission Test).** ISPSC Tagudin Campus's
existing admission examination instrument and its associated application
form; the form's data collection procedures are the basis on which
applicant consent for digital processing is assumed in this study.

**K-Means Aptitude Insights Dashboard.** The live clustering interface
that groups applicant aptitude patterns at the moment of counseling
consultation, extending Yukee et al.'s (2025) validated clustering
configuration into a continuously running decision-support reference.

**NEXIAM.** The role-based college admission testing platform that is the
subject of this study, developed for the Guidance and Registrar Offices
at ISPSC Tagudin Campus.

**Optical Mark Recognition (OMR) / Computer-Vision OMR Scoring.** The
automated bubble-sheet scoring method NEXIAM uses to read scanned or
photographed answer sheets, targeting a baseline classification accuracy
of 95 percent or higher against manual ground truth, with a human
verification guardrail before scores are committed.

**Perceived Ease of Use (PEOU).** A Technology Acceptance Model construct
measuring how effortless a respondent perceives NEXIAM to be to operate.

**Perceived Usefulness (PU).** A Technology Acceptance Model construct
measuring the degree to which a respondent believes NEXIAM improves their
task performance relative to the existing manual process.

**Proctor-Logged Marking.** The structured digital record of exam
markings logged by whichever staff member is assigned proctoring duty for
a given session; proctoring is a duty overlay assignable to any existing
staff role rather than a distinct system role of its own.

**Progressive Web Application (PWA).** A web application architecture
that allows a browser-based interface to retain limited functionality
during a loss of network connection. NEXIAM's proctoring interface uses
PWA service-worker caching to preserve examination session state during
connectivity loss, with synchronization to the cloud database upon
reconnection.

**Proxy Evaluator.** A study participant who steps into a role-based
simulation of system use (for example, an applicant proxy completing a
simulated profile-to-decision workflow) in place of a live system user,
used in this study because the September 2026 evaluation window falls
outside ISPSC Tagudin Campus's active admission cycle.

**Role-Based Access Control (RBAC).** The authorization architecture that
governs every data-access and data-modification operation in NEXIAM by
mapping each of the seven defined user roles, Super Admin, Registrar
Admin, Registrar Staff, Guidance Counselor, Guidance Admin, Program Head,
and Applicant, to its permitted actions.

**Technology Acceptance Model (TAM).** The evaluation framework, grounded
in Davis (1989), used to measure user acceptance of NEXIAM across four
constructs: Perceived Usefulness, Perceived Ease of Use, Attitude Toward
Use, and Behavioral Intention to Use.

**Thematic Analysis.** The qualitative method applied to administrator
questionnaire responses and field observation notes during Requirements
Planning to surface the recurring failure-mode themes that define SO1.

**Use Case.** A discrete, named system interaction available to one or
more actor roles, depicted in the Use Case Diagram (Appendix B) and
governed by the RBAC matrix that determines which roles may trigger it.
