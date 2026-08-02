# Product Scope and Success Contract

**Artifact:** NEXIAM-GF-03  
**Status:** DRAFT — REVIEW REQUIRED  
**Scope:** ISPSC Tagudin admission-testing workflow and bounded post-examination decision support  
**Non-claim:** This draft does not amend the manuscript until approved and propagated.

## 1. Product statement

NEXIAM is a role-isolated admissions-management system for the ISPSC Tagudin admission-testing journey: application intake, controlled applicant access, examination scheduling and administration, scoring, result release, and post-examination counseling support. Its capstone contribution is not “AI everywhere.” It is a complete, auditable institutional workflow with carefully bounded computer-vision and decision-support assistance under human authority.

## 2. Problem boundary

The system should address:

- fragmented Guidance/Registrar handoffs;
- slow and error-prone paper-based scoring and result preparation;
- weak role isolation and broad data visibility;
- inconsistent applicant status communication;
- limited Program Head visibility into approved program-scoped aggregates;
- lack of reliable traceability for critical decisions and reversals;
- peak-volume policy inquiries;
- lack of structured, provenance-preserving counseling support.

The final wording of each problem claim must trace to stakeholder or institutional evidence. Pending claims remain hypotheses in `02-evidence-and-contradiction-register.md`.

## 3. Primary actors

### Evaluated product actors

1. Applicant
2. Registrar Staff
3. Registrar Administrator
4. Guidance Counselor
5. Guidance Administrator
6. Program Head
7. System IT Administrator

### Contextual/system actors

- Proctor: temporary examination-session duty assigned to an eligible staff account; proposed default, not a permanent role.
- Super Admin: non-evaluated break-glass/oversight account, if retained.
- Queue/worker/scheduler: system actors with no human authority.
- External services: mail, knowledge/vector provider, LLM provider, and approved inference worker behind adapters.

Final actor membership is decision-gated by D-ROLE-01 through D-ROLE-05.

## 4. Proposed capability cut-line

### Tier A — Connected core, recommended COMMITTED

| Capability | Required outcome |
|---|---|
| Applicant intake | Create a cycle-scoped admission attempt with validated identity/contact/preferences and duplicate controls |
| Staff review | Registrar can review, correct within authority, accept, dismiss, and reopen through explicit commands |
| Applicant authentication | Non-secret identifier plus credential setup/recovery; own-data isolation |
| Examination scheduling | Guidance creates/publishes sessions, assigns applicants and contextual proctors, records attendance/submission |
| Manual scoring | Authorized Guidance staff can enter/correct scores with finalization completeness rules |
| Consultation and result release | Counselor records decision support/rationale and performs explicit release/unrelease under approved rules |
| Applicant portal | Applicant sees own status, schedule, admission documents, and released results |
| Documents and notifications | Safe, typed generation plus idempotent after-commit delivery |
| Authorization | Approved capability matrix, office/program/own/assigned scopes, denied-path tests |
| Critical audit evidence | Actor-attributed events for identity, role, workflow, score, release, access, and configuration actions |
| Program Head projection | Read-only, program-scoped aggregate visibility if stakeholder scope is approved |

### Tier B — Capstone differentiators, recommended COMMITTED only after spike gates

| Capability | Commitment boundary |
|---|---|
| OMR-assisted scoring | One approved sheet/input contract; uncertainty abstains to human review; manual fallback always available |
| Scoped RAG | Cited institutional answers plus approved own-data retrieval; refusal and staff fallback; no placement advice |
| Human-gated scheduling assistance | Suggestions only; authorized officer confirms; manual scheduling remains complete fallback |
| Counselor decision-support presentation | Independent aptitude/interest signals with versioned provenance; counselor may approve/modify/override/defer |

If any Tier B feature remains in the final manuscript promise, it needs engineering evidence independent of TAM.

### Tier C — CONDITIONAL

- BGE inference service and interest-ranking workflow.
- Yukee reproduction if exact parameters and transforms become available.
- Generic engine administration beyond fixed approved adapters.
- Program quota management rather than read-only visibility.
- In-app messaging beyond a bounded RAG-refusal handoff.
- Offline proctor synchronization.
- Expanded analytics and report inventory.

Conditional means: foundation contract may be designed now, but functional implementation and evaluation proceed only after the named evidence/spike gate passes.

### Tier D — DEFERRED / OUT OF CURRENT STUDY

- autonomous admission, placement, or enrollment decisions;
- predictive-validity or applicant-outcome claims without a valid longitudinal study;
- enrollment, tuition/payment, long-term academic records, or cross-campus resource allocation;
- multi-campus/multi-tenant platform behavior;
- arbitrary third-party executable plugins or model-provided UI code;
- formal NPC certification or a completed PIA claim;
- production/pilot claims based only on synthetic demonstration or TAM.

## 5. Product objectives and evidence

| Objective ID | Objective | Required evidence |
|---|---|---|
| OBJ-01 | Analyze the current admission-testing workflow and operational gaps | Process maps, stakeholder findings, evidence ledger, gap-to-requirement links |
| OBJ-02 | Define and implement a secure role-isolated system for the agreed workflow | Approved product/domain/role contracts, connected browser journey, system tests |
| OBJ-03 | Evaluate perceived usefulness and ease of use for approved respondent groups | Fixed role scenarios, valid TAM instrument/handling, response/exclusion rules, descriptive results |
| OBJ-04 | Demonstrate technical correctness of claimed engineering differentiators | OMR/RAG/model/audit/scheduling benchmarks appropriate to each claim, independent of TAM |

OBJ-04 is an engineering evidence objective. It should not be misrepresented as a new research objective unless adviser review requires manuscript revision.

## 6. Success planes

### Plane 1 — Planning coherence

PASS when:

- product, role, workflow, domain, research, security, and architecture documents agree;
- every committed requirement has evidence, a decision, an acceptance scenario, and future test target;
- no P0 contradiction remains open.

### Plane 2 — Controlled capstone demonstration

PASS when:

- a clean scaffold and seeded dataset reproduce one complete admission-to-release journey;
- allowed and denied authorization paths pass;
- manual fallbacks keep the core workflow operational;
- technical differentiator claims pass their bounded test gates;
- role-specific TAM scenarios are executable;
- limitations and synthetic/sanitized evidence are disclosed.

### Plane 3 — Genuine institutional pilot or production

BLOCKED until:

- institutional workflow and privacy approvals exist;
- real-data handling, retention, backup, recovery, and incident ownership are approved;
- representative technical evidence exists;
- deployment, queue, storage, integration, observability, security, and restore gates pass;
- staff training/support and operational ownership are defined.

A capstone demonstration PASS does not imply pilot or production clearance.

## 7. Evaluation contract

### TAM may establish

- perceived usefulness;
- perceived ease of use;
- role-scenario acceptance within the approved sample and protocol.

### TAM may not establish

- OMR scoring accuracy;
- retrieval grounding or citation correctness;
- scheduler optimality;
- model validity, fairness, or predictive power;
- cryptographic tamper resistance;
- authorization or privacy correctness;
- production scalability or availability.

### Required evaluation preparation

- one fixed scenario set per respondent group;
- task-completion and exclusion rules;
- missing-response handling;
- response-rate reporting;
- interpretation bands approved before analysis;
- separation of technical pilot participants from final TAM sample where required;
- defect log and Go/No-Go outcome from a small technical pilot before broad evaluation.

## 8. First target outcome

The first product milestone is not feature parity. It is the **Connected Admission-to-Release Slice**:

1. submit one admission attempt;
2. review and accept it;
3. establish applicant credentials;
4. schedule and assign one examination;
5. assign a contextual proctor;
6. record attendance/submission;
7. score manually or through confidence-gated OMR;
8. finalize grading;
9. record counselor decision/rationale;
10. release the result;
11. notify the applicant;
12. retrieve the result through the applicant portal;
13. expose approved program-scoped aggregate consequence;
14. verify critical audit evidence and denied access paths.

## 9. Scope-change rule

A capability promised by the final manuscript must be:

- implemented and evaluated;
- explicitly narrowed with adviser/founder approval; or
- clearly labeled future work before implementation evidence is presented.

Quiet omission is prohibited.
