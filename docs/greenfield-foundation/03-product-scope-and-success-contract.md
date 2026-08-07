# Product Scope and Success Contract

**Artifact:** NEXIAM-GF-03  
**Status:** PROMOTED — planning contract (2026-08-05)  
**Story-binding surface:** [`../prd.md`](../prd.md) (full FR/NFR/BR + E2 Connected Slice cut-line)  
**Scope:** ISPSC Tagudin admission-testing workflow and bounded post-examination decision support  
**Non-claim:** Manuscript amendment still requires explicit adviser/founder propagation; quiet omission remains prohibited.

## 1. Product statement

NEXIAM is a role-isolated admissions-management system for the ISPSC Tagudin admission-testing journey: application intake, controlled applicant access, examination scheduling and administration, scoring, result release, and post-examination counseling support. Its capstone contribution is not “AI everywhere.” It is a complete, auditable institutional workflow with carefully bounded computer-vision and decision-support assistance under human authority.

**D-006 founder direction:** The product promise is the whole approved NEXIAM capstone concept and its complete feature boundary, not only the first connected vertical slice. The Connected Admission-to-Release Slice is the first implementation and proof milestone. Every other feature must be deliberately classified, designed, traced to evidence, and sequenced; no capability may be silently omitted.

**D-008 founder direction:** The capstone should plan one shared, provider-agnostic AI capability boundary that can support the RAG chatbot, model/OMR assistance, scheduling assistance, and future AI-assisted interfaces. AI-automated reporting is not part of the current capstone commitment. Exact libraries, SDKs, providers, prompts, model contracts, and implementation details remain subject to later research and HLD/LLD decisions. AI outputs remain supporting evidence or assistance; approved human/application commands remain authoritative. The chatbot may guide an authorized actor through typed, frontend-equivalent task requests, but it never receives a direct database, policy-bypass, or hidden command path.

The detail pass after this decision review must also revisit import architecture. The current separation between application import and score import is observed legacy behavior, not a permanent target boundary. A future unified/dynamic import capability may create related records through one validated, staged, idempotent workflow, subject to domain ownership, authorization, transaction, error-recovery, and audit design.

Where feature families already exist in the manuscript, capstone research, RAD work, or phase plans, the merge process must recover and trace those artifacts into the canonical chain rather than recreate or silently narrow them. The canonical chain is the reconciled design authority; the earlier artifacts remain evidence until promoted through the decision and readiness process.

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
2. Registrar Clerk
3. Registrar Administrator
4. Guidance Counselor
5. Guidance Clerk
6. Program Head

### Contextual/system actors

- Proctor: temporary examination-session duty assigned to an eligible staff account; proposed default, not a permanent role.
- Registrar Support Staff: subordinate/temporary Registrar office staff role with an explicit intake/status/decision subset; it is not Registrar Clerk-equivalent and cannot use institutional/reference setup pages.
- Guidance Support Staff: subordinate/temporary Guidance office staff role with Guidance Clerk responsibilities by default; may receive delegated Test Administration capability or Proctor duty separately.
- Staffing/HR Administrator: personnel-lifecycle actor for approved permanent and support-staff accounts; not an operational applicant/admission actor and cannot grant Test Administrator/Proctor capability.
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
| Applicant authentication | Applicant No/Reference plus post-submission credential setup/recovery; own-data isolation; durable history across cycles |
| Examination scheduling | The authorized Test Administrator holder creates/publishes sessions, controls whether applicant slot reservation is open, assigns applicants and contextual proctors, records attendance/submission, and publishes F2F/online consultation availability; manual assignment and late/direct-assessment paths remain supported |
| Manual scoring | Authorized Guidance staff can enter/correct scores with finalization completeness rules |
| Consultation and result release | Guidance prepares/prints results as required; Counselor/Test Administrator records consultation evidence, preserves suggestions versus Applicant selections, and performs explicit release/unrelease under approved rules |
| Applicant portal | Applicant sees own submitted/accepted status, schedule, consultation availability, and released results; admission slip is not required for answer-sheet identity |
| Documents and notifications | Safe, typed generation plus idempotent after-commit delivery |
| Authorization | Approved capability matrix, office/program/own/assigned scopes, denied-path tests |
| Critical audit evidence | Actor-attributed events for identity, role, workflow, score, release, access, and configuration actions |
| Program Head projection | Read-only, program-scoped aggregate and authorized applicant-record visibility, including result/remark sheet and converted per-domain scores; raw answer sheets and confidential conversion tables remain restricted |

### Tier B — Capstone differentiators, recommended COMMITTED only after spike gates

| Capability | Commitment boundary |
|---|---|
| OMR-assisted scoring | One approved sheet/input contract; uncertainty abstains to human review; manual fallback always available |
| Scoped RAG | Cited institutional answers plus approved own-data retrieval; refusal and staff fallback; no placement advice |
| Human-gated scheduling assistance | Suggestions only; authorized officer confirms; manual scheduling remains complete fallback |
| Offline proctoring | Committed capstone path with encrypted device scope, durable outbox, ordered/idempotent replay, explicit conflict resolution, and visible degraded status; EV-011 gates promotion |
| Counselor decision-support presentation | Independent aptitude/interest signals with versioned provenance; counselor may approve/modify/override/defer |

If any Tier B feature remains in the final manuscript promise, it needs engineering evidence independent of TAM.

### Tier C — CONDITIONAL

- BGE inference service and interest-ranking workflow as a low-priority, feature-flagged beta.
- YuKee aptitude-engine integration from the available trained `.joblib` artifact and engine specifications, with versioned loading/provenance and deterministic expected-output fixtures.
- Generic engine administration beyond fixed approved adapters.
- Program quota management rather than read-only visibility.
- In-app messaging beyond a bounded RAG-refusal handoff.
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

The first implementation milestone is not the whole product boundary. It is the **Connected Admission-to-Release Slice**:

1. submit one admission attempt;
2. review and accept it;
3. establish applicant credentials;
4. either reserve an opened applicant slot or have the authorized Test Administrator manually assign one examination; a special Direct Assessment may bypass ordinary reservation/scheduling;
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
