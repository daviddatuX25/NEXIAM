# Capstone Research and Technical Validation

**Artifact:** NEXIAM-GF-10  
**Status:** DRAFT — REVIEW REQUIRED  
**Purpose:** Align product claims, RAD/AIDLC/CRISP-DM activities, technical evidence, and TAM without overstating what any one method proves.  
**Non-claim:** No benchmark described here has passed until its fixtures, run output, and review evidence exist.

## 1. Evidence separation

| Evidence plane | Question answered | Does not prove |
|---|---|---|
| Workflow/stakeholder evidence | Is this an authentic institutional problem and intended workflow? | Software correctness or model accuracy |
| Automated system tests | Does implemented behavior meet the approved contract? | User acceptance or real-world value |
| Technical benchmark/spike | Does OMR/RAG/model/audit/scheduling meet bounded correctness/performance criteria? | TAM acceptance or predictive validity outside the benchmark |
| Technical pilot | Can representative users complete the connected scenario safely and repeatedly? | Production readiness or broad statistical acceptance |
| TAM | Do approved respondents perceive the system as useful and easy to use? | OMR accuracy, RAG grounding, model validity, security, or scalability |
| Real institutional pilot | Does the system operate acceptably under approved real conditions? | Unbounded production scalability or long-term outcome impact |

## 2. Research claim register

| Claim ID | Proposed defensible claim | Required evidence | Prohibited overclaim |
|---|---|---|---|
| CLM-001 | NEXIAM represents the approved admission-testing workflow and role boundaries | Process maps, stakeholder validation, acceptance trace | “All stakeholders validated” without indexed evidence |
| CLM-002 | NEXIAM supports a complete role-isolated admission-to-release journey | Connected browser/API/database evidence and denied-path tests | Production readiness from a demo |
| CLM-003 | OMR assists scoring on the approved sheet/input conditions and abstains to human review | Ground-truth benchmark, uncertainty review, throughput, idempotent import | Universal scanner/camera accuracy or zero error |
| CLM-004 | RAG returns cited answers from an approved corpus and refuses unsupported/out-of-scope queries | Curated query set, citation support, refusal, authorization, injection/outage tests | Truthfulness from TAM or autonomous counseling |
| CLM-005 | Scheduling assistance proposes a feasible plan under encoded constraints for human confirmation | Constraint fixtures, feasibility checks, comparison/fallback | Globally optimal schedule unless proven by a defined objective/solver |
| CLM-006 | Admission-support engines present independent, versioned signals for counselor review | Reproducible transforms, mapping evidence, provenance, override/defer tests | Autonomous recommendation correctness or predictive validity |
| CLM-007 | Critical events are tamper-evident under the declared threat model | Canonicalization/concurrency/tamper/key/checkpoint tests | “Tamper-proof” against total platform/key compromise |
| CLM-008 | Evaluated respondents report measured TAM PU/PEOU under approved scenarios | Final instrument, protocol, handling, results | Technical correctness or institution-wide acceptance |

## 3. OMR validation contract

### Founder UX direction for the OMR review workspace

The OMR feature should include a powerful PWA-capable review workspace in which an authorized reviewer can move through a scan batch smoothly, inspect the full paper and bubble regions, correct detected marks, explicitly confirm blank/no-bubble answers, and preserve confidence, provenance, before/after values, reviewer attribution, and audit history. PWA delivery is an interface requirement, not permission for offline authoritative mutation; offline writes remain subject to the conflict-controlled synchronization decision and gate.

### Required inputs before implementation commitment

- approved answer-sheet template and version;
- answer-key representation and versioning;
- scanner/camera/input channels actually available;
- blank, correctly marked, ambiguous, multiple-mark, erased, skewed, rotated, faint, damaged, and wrong-template examples;
- anonymized or synthetic ground truth;
- batch size and operational time expectation;
- approved low-confidence review workflow and manual fallback;
- raw-image storage/access/retention rule.

### Benchmark dataset proposal

Use a versioned corpus containing:

- controlled synthetic sheets for geometry/mark edge cases;
- representative approved captures from actual intended devices;
- independently labeled bubble and sheet ground truth;
- holdout fixtures not used to tune thresholds;
- provenance for template, device, resolution, lighting/scanner mode, and labeling reviewer.

Final sample size and thresholds are D-RES-02 decisions. The corpus must be large/diverse enough to expose operational failure classes; a few ideal sample sheets are demonstration fixtures, not validation.

### Required metrics

- template-detection success/failure;
- bubble-level precision/recall or exact classification accuracy by class;
- sheet-level exact-score accuracy;
- low-confidence/ambiguous-case recall;
- false auto-accept rate;
- wrong-template and malformed-input rejection;
- throughput and resource use at target batch size;
- idempotent reprocessing/import;
- human-review correction rate and time;
- no-silent-failure count.

### Safety gate

OMR may automatically propose/import only values above the approved confidence and validation rules. Uncertain, malformed, wrong-template, contradictory, or incomplete sheets must enter review or manual scoring. Manual scoring remains a complete workflow.

## 4. RAG validation contract

### Corpus contract

Each institutional source has:

- owner and approver;
- title/type/version/effective dates;
- extraction/sync status;
- allowed audience/data class;
- citation identity;
- supersession/revocation path;
- update owner and review cadence.

### Query-set classes

- direct in-corpus policy questions;
- multi-source questions;
- stale/superseded-policy traps;
- unsupported/out-of-corpus questions;
- prohibited placement/recommendation prompts;
- prompt-injection content and user attempts;
- authorized own-result/schedule queries;
- pre-release attempts to retrieve own scores, consultation, or result data;
- cross-applicant/unauthorized-data attempts;
- ambiguous identity/scope requests;
- provider timeout/unavailability.
- refusal/no-support cases with static staff fallback and applicant-confirmed bounded inquiry behavior if retained.

### Required metrics/evidence

- retrieval relevance/recall on the curated set;
- citation presence and source support;
- unsupported-claim rate;
- refusal precision/recall for prohibited/unsupported cases;
- authorization isolation with zero cross-applicant disclosure in the suite;
- provider latency/error and degraded fallback;
- token/cost budget where applicable;
- human review of representative answer usefulness.

TAM may measure perceived usefulness/ease of use after these engineering gates; it cannot replace them.

## 5. Yukee/deterministic aptitude engine contract

Two honest implementation paths exist:

### Path A — Reproducible adoption

Allowed only if the exact source definition is available:

- input features and units;
- preprocessing/normalization/percentile transform;
- centroid/cluster parameters and labels;
- distance/assignment function;
- source dataset applicability and version;
- deterministic fixtures reproducing expected assignments.

### Path B — NEXIAM deterministic aptitude-profile baseline

If exact parameters are unavailable:

- do not call it a reproduction of Yukee K-Means;
- define transparent rules/thresholds with domain-expert review;
- name it a deterministic baseline inspired by prior aptitude-profile findings;
- version every rule/mapping;
- document limitations and no predictive-validity claim.

Cluster-to-program mapping is separate from cluster/profile computation. It requires Program Head validation and cannot be presented as model output.

## 6. BGE interest engine contract

If retained:

- inputs should be genuine interest/goal narratives or another approved source, not merely restating a selected program and “rediscovering” it;
- program corpus must use approved descriptions, outcomes, and versioned sources;
- embedding model/runtime/version and text preprocessing are fixed;
- output is a ranked similarity signal with evidence, not a supervised prediction;
- evaluation uses counselor/Program Head relevance judgment on realistic cases;
- repeatability, schema, timeout, unavailable-worker, and provenance tests pass;
- applicant preference and aptitude signals remain independent in storage/presentation.

No “supervised model” claim is allowed unless labeled training data, training procedure, validation, and metrics actually exist.

## 7. Scheduling-assistance validation

The baseline manual scheduler must satisfy all workflow requirements.

Any AI/optimization assistant must define:

- hard constraints: room/time overlap, capacity, cycle/session eligibility, staff availability where approved;
- soft objectives and explicit weights;
- feasible versus infeasible response;
- deterministic fixture set;
- human confirmation/edit/reject path;
- explanation of violated/relaxed soft constraints;
- timeout/outage fallback to manual scheduling;
- no “optimized” claim without a defined objective and comparative evidence.

## 8. Decision-support and counselor authority

- Aptitude and interest results are independent evidence objects.
- Every result includes engine/version, input hash, timestamp, confidence/score, and limitations.
- Counselor actions are `approve`, `modify`, `override`, or `defer`, with rationale categories and optional notes under approved privacy rules.
- Inference never directly releases, admits, rejects, or changes an applicant’s authoritative state.
- Program Head mapping/visibility does not become autonomous decision authority.
- Override/defer patterns and mapping still require stakeholder evidence.

## 9. Audit technical validation

Required fixtures include:

- stable canonical JSON across key ordering/serialization boundaries;
- two concurrent critical event appends;
- modified payload detection;
- deleted event detection;
- reordered/inserted event detection;
- key rotation preserving historical verification;
- external checkpoint match/mismatch;
- append-only application privilege denial;
- verifier/checkpoint outage and recovery;
- redaction of credentials/secrets/sensitive payloads.

The final report must state the exact attacker capabilities the evidence covers.

## 10. Technical pilot before TAM

Proposed minimum controlled pilot:

- one Guidance participant;
- one Registrar participant;
- one Program Head participant;
- three to five applicant/student test accounts;
- synthetic or approved sanitized data;
- one complete connected journey plus recovery/denied scenarios;
- defect, severity, owner, resolution, and Go/No-Go log.

Measure:

- task completion;
- scenario duration;
- authorization/data-scope failures;
- missing/incorrect data;
- recovery success;
- critical system/browser errors;
- operational/manual fallback use.

Pilot participants are not automatically included in the final TAM sample unless the research protocol permits it.

## 11. TAM protocol hardening

Before final administration, approve:

- respondent groups and counts/population basis;
- fixed role-specific tasks/screens;
- item-to-feature mapping;
- inclusion/exclusion and incomplete-response rules;
- missing-response handling;
- response-rate reporting;
- predeclared interpretation bands;
- consent and data handling;
- distinction between requirement-validation participants, technical pilot participants, and final evaluators;
- reporting limitations for small staff-group counts.

Report incident counts alongside percentages for small groups to avoid false precision.

## 12. Research Go/No-Go gates

| Gate | GO evidence | NO-GO consequence |
|---|---|---|
| RES-G1 Workflow evidence | Required process/authority artifacts indexed and contradictions decided | Do not claim stakeholder-validated workflow |
| RES-G2 OMR | Approved input corpus and pass thresholds; uncertainty/manual path works | Keep manual scoring; present OMR only as bounded prototype or defer |
| RES-G3 RAG | Approved corpus/query set; citation/refusal/auth tests pass | Remove/narrow chatbot claim or use static FAQ fallback |
| RES-G4 Yukee | Exact reproducible model or approved honest deterministic baseline | Do not claim adopted K-Means reproduction |
| RES-G5 BGE | Approved corpus/input semantics and relevance evidence | Defer BGE; preserve adapter seam only |
| RES-G6 Scheduling | Constraint contract and feasible/manual fallback evidence | Ship manual scheduling; remove optimization claim |
| RES-G7 Audit | Threat model and technical suite pass | Use conventional immutable audit log language only; no cryptographic assurance claim |
| RES-G8 TAM | Technical pilot passes; scenarios/instrument/handling approved | Do not begin broad evaluation |

## 13. Decisions

- D-RES-01 separation of TAM and technical correctness claims.
- D-RES-02 OMR corpus, thresholds, devices, review SLA, retention.
- D-RAG-01 policy-only versus authorized own-data scope.
- D-RAG-03 corpus/query ownership and measurable thresholds.
- D-ENG-01 reproducible Yukee versus honest deterministic baseline.
- D-ENG-02 BGE inclusion, input semantics, and evidence.
- D-SCHED-01 manual baseline and scope of scheduling assistance.
- D-AUD-01 threat-model claim and technical validation.
- D-EVAL-01 technical-pilot protocol.
- D-EVAL-02 final TAM respondent/scenario/handling contract.
- D-INQUIRY-01 policy-corpus versus released-own-data gates and confirmed refusal handoff.
