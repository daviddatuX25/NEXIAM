# NEXIAM Phase 2 — IPO & Scope Working Notes
## All David directives consolidated | For reference during drafting

---

## IPO INPUT

### Institutional Data
- Applicant profiles *(identifier key embedded here)*
- Exam and schedule preferences
- Admission inquiries
- Advisory notes *(was "Triage advisory notes")*
- Scanned answer sheets
- Program quotas
- Activity logs

### Development Foundations *(things used IN the process, not just evaluated)*
- Computer vision OMR engine *(general — not locked to OMRChecker; could be OpenCV-based)*
- AI orchestration framework *(general — not locked to Laravel AI SDK)*
- Admission support models *(was "Supplementary ML models"; panel rejected "decision support")*

---

## IPO PROCESS
Development and Implementation of NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin

---

## IPO OUTPUT *(grouped per panel recommendation)*

### Communication
- Chatbot responses
- Email updates *(replaces Status notifications)*

### Platform Access
- Applicant portal *(accessed via identifier key, not traditional account)*
- Staff interface *(RBAC-governed dashboards for Guidance, Registrar, Program Heads)*
- Audit trails *(HMAC-SHA256)*

### Scheduling
- Optimized exam schedules *(AI-assisted, human-gated)*

### Reports and Results
- Exam scores and prints *(term maintained)*
- Results and release sheet *(new)*
- Pre-enrolment lists *(was "Pre-enrolment slates")*

### Analytics
- Admission support dashboards *(was "Aptitude dashboards" / "Decision-support triage")*

---

## WHAT WAS REMOVED
- ❌ Status notifications → replaced by Email updates
- ❌ Decision-support triage → replaced by Admission support dashboards
- ❌ "Triage" prefix → Advisory notes
- ❌ Specific library names (OMRChecker, Laravel AI SDK) → general terms
- ❌ Traditional applicant portal auth → identifier key system

---

## SCOPE & LIMITATIONS — PER-COMPONENT

### OMR
- Gated decisioning: flagged marks → human verification, not auto-scored
- No standalone accuracy metric
- Validated through TAM (PU items: automated vs manual scoring)
- NOT per-sheet accuracy benchmarking against ground truth

### RAG Chatbot *(NOT FAQ — structural service layer)*
- Connected to AI scheduling engine
- Applicant retrieves OWN results
- Applicant checks OWN schedule
- Pulls available slots, constraints, optimized assignments from scheduling DB
- Surfaces AI-processed output in natural language
- Restricted to institutional knowledge base
- Below confidence threshold → declines to answer (no fabrication)
- Evaluated through TAM PU/PEOU
- Free-tier inference only; paid capacity out of scope

### Admission Support Models (ML Engine) *(David's paragraph — verbatim)*
> The machine learning component of this study is bounded strictly to the architectural design and functional integration of an extensible interface wrapper within the admission system. While the module is engineered as a pluggable framework capable of loading external data assets — specifically an unsupervised model for Historical Cohort Profiling and a simple baseline supervised model for Preliminary Program Matching — the intrinsic mathematical accuracy, parameter optimization, algorithmic tuning, and predictive validity of these underlying models remain completely unmeasured and outside the scope of this research. The evaluation of this module is restricted purely to its system utility, multi-role data serving capabilities, and user acceptance testing.

### Normal Admission Functions
- Registration, scheduling, result processing, inter-office coordination
- RBAC-governed dashboards
- Portal access via unique identifier key (embedded in applicant profile)
- Email notifications for schedules and results
- 24/7 access without physical campus visits

### Audit Trail
- HMAC-SHA256 cryptographic chaining
- Covers: data access, role actions, placement decisions, schedule modifications
- Evaluated by IT Expert via separate technical walkthrough (qualitative)

---

## ADDITIONAL LIMITATIONS
- Admission testing function only (no enrollment, tuition, records, cross-campus)
- Cloud-hosted deployment (local hardware constraints)
- Proctoring: offline-resilient via service-worker caching; all else requires connectivity
- Free-tier OpenRouter inference caps request volume
- Simulated evaluators (September window outside active cycle)
- No NPC certification; application-layer compliance only
- Applicant consent assumed under ICAT form; formal verification = future work

---

## PENDING
- *(None — all directives captured. Ready for manuscript drafting when David confirms.)*
