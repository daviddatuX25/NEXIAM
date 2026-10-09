# Specific Objective 2: System Requirements and the Rapid Application Development Process

This section presents the requirements and development account for Specific Objective 2. It follows the RAD phases of Requirements Planning, User Design, Rapid Construction, and Cutover (Martin, 1991; Pressman & Maxim, 2020). The section reserves locations for the full use-case mapping, design figures, feature evidence, and system plates.

**Functional Requirements.**

Table 3.3 retains thirteen functional requirement identifiers for the draft mapping. It connects the process concerns in SO1 with the intended system functions. The approved use-case documentation will provide the final actor responsibilities, actions, and conditions.

**Table 3.3**  
*Proposed Functional Requirements of NEXIAM: Mapping and Verification Pending*

| ID | Proposed functional requirement for review | Proposed trace | Status |
|---|---|---|---|
| FR-01 | Support applicant intake with validated data, applicable privacy notice/consent handling, and a reference identifier. | FM5; decision mapping to confirm | Proposed; use-case and evidence review pending |
| FR-02 | Support attributable Registrar review of submitted requirements and record-status actions. | FM5; decision mapping to confirm | Proposed; state/action mapping pending |
| FR-03 | Support staff-managed examination session setup with room and roster information. | FM3; decision mapping to confirm | Proposed; actor and capacity rules pending |
| FR-04 | Support applicant self-booking only where enabled by an authorized policy decision; staff-managed assignment remains the documented operational default. | FM3; decision mapping to confirm | Optional/policy-gated capability; not an evaluated task |
| FR-05 | Support answer-sheet scoring, including any automated recognition and human review paths established by approved design and evidence. | FM1; decision mapping to confirm | Proposed; exact runtime behavior and limits pending |
| FR-06 | Support recording and review of examination scores and batch state under approved completion and correction rules. | FM1; decision mapping to confirm | Proposed; gate and audit evidence pending |
| FR-07 | Support recording of post-examination consultation and its outcome according to approved workflow. | FM4; decision mapping to confirm | Proposed; role/state mapping pending |
| FR-08 | Support recording program preferences and relevant departmental capacity information for authorized use. | FM4; decision mapping to confirm | Proposed; data and decision boundary pending |
| FR-09 | Support result preparation and delivery modes permitted by approved policy, with attributable state changes. | FM2; decision mapping to confirm | Proposed; physical delivery is the evaluation scenario; other modes not evaluated |
| FR-10 | Support Registrar review of enrollment eligibility information using approved criteria. | FM2; decision mapping to confirm | Proposed; criteria and authority pending |
| FR-11 | Support an applicant-facing view of authorized admission milestones and schedule/result information when available. | FM2; decision mapping to confirm | Proposed; exact visibility and delivery states pending |
| FR-12 | Apply approved capability- and resource-scoped access rules and record relevant actions as specified by the approved security design. | FM3, FM5; decision mapping to confirm | Proposed; security and audit behavior verification pending |
| FR-13 | Support applicant inquiries and cross-office responses within approved scope and privacy boundaries. | FM3; decision mapping to confirm | Proposed; actor/thread scope pending |

The requirement identifiers remain stable during review. Update the requirement wording and trace links when the approved use-case and decision documentation is integrated.

The full system use-case, data-flow, and entity-relationship diagram material is located in Appendices C to F. The main discussion focuses on the requirements and features linked to the SO1 findings.

**System Features.**

Table 3.4 organizes the feature areas across the admission-testing lifecycle. It distinguishes preparation activities, user tasks included in the evaluation, and optional functions. Implementation records and the approved use-case mapping will complete the feature descriptions and evidence status.

**Table 3.4**  
*System Feature Areas and Evidence Status for Review*

| Lifecycle area | Feature area retained for review | Related FR identifiers | Scope/status distinction |
|---|---|---|---|
| Configuration and setup | Administrative setup and template/catalog configuration | FR-05, FR-06, FR-08, FR-09, FR-12 | Researcher preparation/configuration is not a TAM participant task; verify the live setup scope |
| Configuration and setup | Staff views and role-scoped access | FR-12 | Verify actor/resource boundaries against approved use cases and technical evidence |
| Application intake | Online intake and document review | FR-01, FR-02 | Not a substitute for evidence of each validation, consent, or review behavior |
| Application intake | Applicant status and self-service portal | FR-01, FR-04, FR-11 | Assigned student tasks are listed in Appendix N; booking remains optional/policy-gated |
| Application intake | Inquiry and administrative response | FR-11, FR-13 | Student inquiry/reply and Guidance-to-academic-leader thread exchange are in the planned scenarios; verify exact scope |
| Examination scheduling | Session management and booking options | FR-03, FR-04 | Staff-managed scheduling is evaluated; applicant self-booking is optional and not evaluated |
| Examination day | Roster and attendance recording | FR-03 | Specific task behavior to be recorded; no claim of offline resilience without evidence |
| Result preparation | OMR scoring and review | FR-05 | Ordinary prepared-sheet scoring is in a Guidance scenario; uncertain-mark adjudication is not evaluated |
| Result preparation | Score recording and batch state | FR-06 | Prepared mappings/keys and configuration are setup; completeness behavior requires separate evidence |
| Consultation and triage | TabPFN or other configured program-match review | FR-07, FR-08 | Match-review task planned; no model-performance or admission-probability claim; readiness evidence required below |
| Consultation and triage | Consultation workbench and capacity information | FR-07, FR-08 | Face-to-face consultation and physical result delivery are in the planned staff/student scenarios; verify exact functions |
| Result handoff | Result preparation, printing, portal release, and reversal paths | FR-09 | Physical result-sheet delivery is in the planned scenario; separate digital release is not evaluated |
| Post-admission | Eligibility information and reports | FR-08, FR-10 | Registrar tasks cover specified lookups/review; establish criteria and evidence before claiming automated eligibility |
| Cross-cutting | Audit/event records | FR-12 | Verify actual event coverage, integrity behavior, and security claims before reporting |

The evaluated task scope is defined in Section 3.3 and Appendix N. Broader or optional capabilities remain separate from the selected tasks and require their own policy and evidence records.

**RAD Development.**

**Requirements Planning.**

Requirements Planning used the process inquiry and stakeholder material in Appendices G to I. Table 3.2 links the recorded concerns to proposed requirements. The two applicant survey cohorts are kept separate when reporting their experiences.

**User Design.**

User Design covers the system boundary, actor responsibilities, data flows, data model, and interface prototypes. The full design-diagram material is located in Appendices C to F and is reconciled with the requirements and current design records.

**Rapid Construction.**

The development account covers a Laravel application with Inertia and Svelte interfaces, PostgreSQL storage, OMR scoring, program-match decision support, and audit records. The final account will identify the evaluated build and attach the technical evidence for each reported behavior.

TabPFN provides the research basis for the tabular program-match component (Hollmann et al., 2025). Its intended use is to support Guidance review of applicant information against institutional benchmark exemplars. The model and benchmark records will identify the engine used in the evaluated build. The program-match output remains advisory.

[EVIDENCE: Record the evaluated build/runtime identity; selected admission-engine provider and actual model/driver/version; whether inference completed in that environment; benchmark-set identity/version and readiness state; and a traceable invocation/output/timing record. Revise model wording to match that evidence. Do not infer calibrated probabilities, predictive accuracy, admissions suitability, or autonomous admission from the existence of a TabPFN citation, adapter, synthetic fixture, or UI card.]

**Cutover.**

Cutover covers preparation for the role-specific tasks and TAM questionnaire. Section 3.3 provides the planned sample and the result-table locations. Deployment and task records will complete the development account, while questionnaire responses will supply the acceptance results.

[EVIDENCE: For every technical behavior reported as tested, record the exact actor/task or trigger, environment/build, input and preconditions, observed behavior/result, failures/help or manual intervention, and limits. Replace only the corresponding provisional wording; do not generalize beyond the observed behavior.]

**System Plates.**

Plates 1 to 15 reserve the main system interfaces and result artifact. Following the presentation pattern in Yukee et al. (2025), each plate has an image location and an explanatory paragraph. Insert verified captures using synthetic or redacted data, then revise each paragraph to match the visible interface. Record the role, build, and displayed state with the capture; technical behavior is supported by separate records.

### Plate 1: Public Applicant Intake

[PLATE 1: Public intake. Insert a verified screenshot; show only relevant visible fields and use synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real public intake screen, showing only its visible form fields and submission controls with synthetic/redacted records.
> - **Source:** This section, Table 3.4 and Plate 1; public intake is broader system documentation, not a student TAM task.
> - **Confirm:** Capture environment, build, and visible state; verify each described field/control against the image.

**Plate 1.** Public Applicant Intake.

The intake plate covers the form used by a prospective applicant. The final description will identify the fields, requirements information, and submission controls visible in the approved capture.

### Plate 2: Applicant Milestone and Schedule View

[PLATE 2: Applicant milestone view. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real applicant milestone and assigned-schedule view with synthetic/redacted data; show only what is visible.
> - **Source:** This section, Plate 2; student status and schedule tasks in Appendix N.
> - **Confirm:** Verify displayed state and available navigation from the captured screen; do not imply self-booking.

**Plate 2.** Applicant Milestone and Schedule View.

This plate covers the applicant's milestone and assigned-schedule view. Its description will identify the displayed admission state, examination information, and available navigation.

### Plate 3: Registrar Intake Review

[PLATE 3: Registrar intake review. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the actual Registrar intake-review screen with synthetic/redacted data, including only visible applicant, requirement-status, and review controls.
> - **Source:** This section, Plate 3; Registrar tasks in Appendix N.
> - **Confirm:** Verify the reviewer role, displayed fields, and controls against the capture.

**Plate 3.** Registrar Intake Review.

The Registrar intake plate covers application review. Its description will identify the applicant information, requirement status, and review controls visible in the selected state.

### Plate 4: Examination Session Management

[PLATE 4: Examination session management. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real staff-managed session screen with synthetic/redacted data; show only visible scheduling, room, and staff controls.
> - **Source:** This section, Plate 4; scheduling task in Appendix N.
> - **Confirm:** Verify visible details; do not imply applicant self-booking.

**Plate 4.** Examination Session Management.

The examination-session plate covers staff-managed scheduling. Its description will identify the session details, assigned room, capacity information, and staff controls used in the selected workflow.

### Plate 5: Examination Roster and Attendance

[PLATE 5: Examination roster and attendance. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real examination roster and attendance state with synthetic/redacted data; include only visible recording controls.
> - **Source:** This section, Plate 5; Guidance attendance task in Appendix N.
> - **Confirm:** Distinguish examination attendance from consultation attendance.

**Plate 5.** Examination Roster and Attendance.

The roster plate covers examination attendance. Its description will identify the examinee list, attendance state, and recording controls visible to the assigned staff user.

### Plate 6: Scoring Interface

[PLATE 6: Scoring interface. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the actual prepared-sheet scoring workflow with synthetic/redacted data, showing only its real input, processing state, and displayed result.
> - **Source:** This section, Plate 6; prepared scoring task in Appendix N.
> - **Confirm:** Verify displayed behavior; accuracy and processing time require separate technical records.

**Plate 6.** Scoring Interface.

The scoring plate covers use of the prepared scoring function. Its description will identify the submitted sheet, displayed processing state, and resulting scores in the selected capture. Processing time and recognition accuracy require separate technical records.

### Plate 7: Mark Review Interface

[PLATE 7: Mark review interface. Insert a verified screenshot only if this function is available in the captured environment; use synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture a real mark-review screen only if available in the evaluated environment; use synthetic/redacted data and show visible controls.
> - **Source:** This section, Plate 7; system-feature scope in Table 3.4.
> - **Confirm:** Capability and capture availability are unverified; this is not a TAM task.

**Plate 7.** Mark Review Interface.

The mark-review plate is reserved for separately documented review capability. It is outside the TAM task set. Its description will identify the mark and review controls only after the capability and capture are confirmed.

### Plate 8: Score Completion or Batch State

[PLATE 8: Score completion or batch state. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the actual score-completion or batch-state screen with synthetic/redacted data; show only displayed scores, status, and actions.
> - **Source:** This section, Plate 8; score-state scope in Table 3.4.
> - **Confirm:** Verify visible state; completion rules need approved use-case and technical evidence.

**Plate 8.** Score Completion or Batch State.

The score-state plate covers score completion or batch status. Its description will identify the scores, displayed status, and available actions in the selected state. The completion rules will be described from the confirmed use-case and technical evidence.

### Plate 9: Program-Match Review

[PLATE 9: Program-match review. Insert a verified screenshot with synthetic/redacted data and identify the model/provider only if runtime evidence confirms it.]
> **Slot metadata (working note):**
> - **Brief:** Capture Guidance's real program-match review with synthetic/redacted data; include matches only if displayed.
> - **Source:** This section, Plate 9; Guidance review task in Appendix N.
> - **Confirm:** Verify runtime engine/provider separately; a screenshot does not establish model performance.

**Plate 9.** Program-Match Review.

The program-match plate covers the information reviewed by Guidance during consultation. The description will identify the displayed program matches and any benchmark comparison. The TabPFN research basis is retained (Hollmann et al., 2025); the actual engine and benchmark version will be supplied from the model evidence.

### Plate 10: Guidance Consultation

[PLATE 10: Guidance consultation. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real face-to-face consultation workflow with synthetic/redacted data, showing only visible applicant information, controls, and outcome.
> - **Source:** This section, Plate 10; Guidance consultation task in Appendix N.
> - **Confirm:** Verify the selected state; do not present it as online consultation.

**Plate 10.** Guidance Consultation.

The consultation plate covers the face-to-face workflow and its recorded outcome. Its description will identify the applicant information, consultation controls, and outcome shown in the selected state.

### Plate 11: Result Preparation and Delivery

[PLATE 11: Result preparation/delivery. Insert a verified screenshot or physical result-sheet image using synthetic/redacted data; label the delivery mode shown.]
> **Slot metadata (working note):**
> - **Brief:** Capture the real prepared result artifact or physical result sheet with synthetic/redacted data; name only the delivery mode shown.
> - **Source:** This section, Plate 11; face-to-face delivery scenario in Appendix N.
> - **Confirm:** Verify printed content and delivery evidence separately; do not imply online release.

**Plate 11.** Result Preparation and Delivery.

The result plate covers preparation and physical delivery of the system-generated result sheet. Its description will identify the information printed on the sheet and the delivery state used in the face-to-face scenario.

### Plate 12: Academic Leader View

[PLATE 12: Academic leader view. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture a real Program Head or Dean view with synthetic/redacted data; show only visible authorized capacity, applicant, or referral content.
> - **Source:** This section, Plate 12; academic-leader tasks in Appendix N.
> - **Confirm:** Verify role and program scope; this is not the complete system actor map.

**Plate 12.** Academic Leader View.

The academic-leader plate covers the view used by a Program Head or Dean. Its description will identify the assigned program scope, capacity information, applicant list, and referral controls present in the approved capture.

### Plate 13: Registrar Handoff or Eligibility View

[PLATE 13: Registrar handoff/eligibility view. Insert a verified screenshot with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the actual Registrar handoff/eligibility view with synthetic/redacted data, showing visible status and supporting information only.
> - **Source:** This section, Plate 13; Registrar eligibility task in Appendix N.
> - **Confirm:** Verify visible state and authorized information; the image does not establish criteria.

**Plate 13.** Registrar Handoff or Eligibility View.

The Registrar handoff plate covers enrollment-eligibility information. Its description will identify the displayed status and supporting information used by Registrar personnel.

### Plate 14: Audit or Event Record View

[PLATE 14: Audit/event record view. Insert a verified screenshot with synthetic/redacted data, only if present in the evaluated environment.]
> **Slot metadata (working note):**
> - **Brief:** Capture an authorized event-record screen with synthetic/redacted data only if it exists in the evaluated environment.
> - **Source:** This section, Plate 14; event-record scope in Table 3.4.
> - **Confirm:** Capture availability is unverified; integrity needs separate technical evidence.

**Plate 14.** Audit or Event Record View.

The event-record plate covers the recorded actions available to an authorized user. Its description will identify the visible event, actor, and date fields. Claims about record integrity require the related technical evidence.

### Plate 15: Office Coordination Thread

[PLATE 15: Office Coordination. Insert a verified screenshot showing the explicit Guidance ↔ academic department thread, with synthetic/redacted data.]
> **Slot metadata (working note):**
> - **Brief:** Capture the actual paired Guidance–academic department thread exchange with synthetic/redacted data, showing only case context and visible messages.
> - **Source:** This section, Plate 15; paired communication tasks in Appendix N.
> - **Confirm:** Verify both sides and displayed scope; do not expose real applicant data.

**Plate 15.** Office Coordination Thread.

The office-coordination plate covers the applicant-thread exchange between Guidance and an academic department. Its description will identify the case context, message, and reply visible in the paired task, using synthetic or redacted records.

**Discussion.**

The system account links the SO1 process findings to requirements, design outputs, and the interfaces reserved in the plate sequence. The full use-case documentation will complete the actor and feature mapping. Technical records will supply the observed behavior for the evaluated build.

The selected user tasks use staff-managed sessions, prepared scoring, face-to-face consultation, physical result-sheet delivery, and recorded office communication. Preparation activities and optional modes remain separate from this task set.

[EVIDENCE: Complete the SO2 discussion after integrating the approved use-case mapping, design figures, verified plates, and technical records. Relate the observed functions and remaining limits to the source process concerns.]
