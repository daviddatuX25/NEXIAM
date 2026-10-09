# Appendix O: Use-Case Coverage Register and Actor-Lane Activity Diagrams

**Status:** Engineering documentation supplement. This appendix is not an institutional acceptance certificate, a respondent evaluation result, or proof that every possible system input has been validated.

## Purpose

The NEXIAM use-case audit records what an applicant, an authorized staff member, or a background process can request from the greenfield application. Each use-case identifier connects its actor, prerequisites, ordinary path, exceptional branches, state changes, and handoffs to implementation and contract evidence. The same identifiers can support a system manual and controlled testing scenarios without maintaining a separate description of the process for each audience.

The canonical engineering source is the [system use-case audit register](../../../openspec/changes/action-core-authority-realignment/use-case-audit/README.md), under the existing `action-core-authority-realignment` inventory change. Application behavior remains governed by its own approved domain change. This appendix does not authorize a repair or change institutional policy.

## Relationship to the existing visual models

Appendix C retains the institutional use-case diagram. Appendices D and E retain the context and decomposition data-flow diagrams, and Appendix F retains the entity-relationship model. Those artifacts are not replaced by this audit.

The activity views add the decisions and responsibilities that the overview diagrams do not show. Registrar acceptance does not publish an examination schedule. A recorded attendance outcome does not finalize a score. A generated PDF does not prove physical printing. A consultation decision, a Program Head clearance, an applicant selection, and a Registrar enrollment stamp remain separate records. Actor lanes make these boundaries visible at the point where one office hands work to another.

The new diagrams use Mermaid flowcharts with actor and system lanes. They are activity views, not a claim of complete formal UML conformance. Each view has a source document and use-case context. An implemented branch that conflicts with its controlling contract is identified as a discrepancy, not silently rewritten into an ideal process.

## Domain catalogue

| Domain | Register | Main responsibility boundary |
|---|---|---|
| Identity and personnel | [IDN](../../../openspec/changes/action-core-authority-realignment/use-case-audit/identity.md) | Staff authentication, activation, security settings, personnel management, and delegation |
| Institutional setup | [SET](../../../openspec/changes/action-core-authority-realignment/use-case-audit/setup.md) | Reference configuration and readiness versus operational proof |
| Admissions and applicant access | [ADM](../../../openspec/changes/action-core-authority-realignment/use-case-audit/admissions.md) | Public discovery, intake, Registrar decisions, access, and own-record views |
| Examination | [EXM](../../../openspec/changes/action-core-authority-realignment/use-case-audit/examination.md) | Scheduling, publication, reservation, duty, attendance, accommodation, and closure |
| Grading | [GRD](../../../openspec/changes/action-core-authority-realignment/use-case-audit/grading.md) | Eligible roster, score recording, conversion, OMR adapter handoffs, and finalization |
| OMR | [OMR](../../../openspec/changes/action-core-authority-realignment/use-case-audit/omr.md) | Materials, authoring, qualification, issuance, diagnostic and production capture, review, and score commit |
| Result documents | [DOC](../../../openspec/changes/action-core-authority-realignment/use-case-audit/documents.md) | Templates, prepared artifacts, manufacturing, print attestation, release, and private downloads |
| Consultation | [CON](../../../openspec/changes/action-core-authority-realignment/use-case-audit/consultation.md) | Session planning, counselling, applicant choice, referrals, and online encounters |
| Admission support and governance | [SUP](../../../openspec/changes/action-core-authority-realignment/use-case-audit/support.md) | Advisory models, benchmark revisions, program notes, discussions, quotas, and scoped governance |
| Enrollment and reports | [ENR](../../../openspec/changes/action-core-authority-realignment/use-case-audit/enrollment-reports.md) | Readiness, paper-verified stamps, reversals, reporting, dashboards, and audit views |
| Communications | [COM](../../../openspec/changes/action-core-authority-realignment/use-case-audit/communications.md) | Typed recipients, policy, notices, delivery recovery, inbox state, and case messaging |
| Ingestion | [IMP](../../../openspec/changes/action-core-authority-realignment/use-case-audit/ingestion.md) | Analysis, durable staging, Registrar consent, domain commit, and Guidance continuation |
| Xiam and knowledge | [XIA](../../../openspec/changes/action-core-authority-realignment/use-case-audit/xiam-knowledge.md) | Knowledge governance, grounded inquiry, proposals, human confirmation, and run recovery |
| System operations | [OPS](../../../openspec/changes/action-core-authority-realignment/use-case-audit/operations.md) | Restricted demo preparation, diagnostics, storage, background entrypoints, and development references |

## Activity diagram catalogue

The [activity diagram catalogue](../../../openspec/changes/action-core-authority-realignment/use-case-audit/ACTIVITY-DIAGRAMS.md) supplies the source location, actor-lane title, and rendered SVG for each view. The source remains in the domain register; the SVG is a derived illustration. Exported figure labels use stable engineering identifiers rather than invented manuscript page numbers.

The catalogue contains separate views for materially different processes. Authoring an answer sheet, qualifying its physical evidence, and committing reviewed production scores are not one activity. Notification delivery, notification reading, and notice-template publication also have different actors and state owners.

## What the evidence establishes

| Evidence level | What may be stated | What it does not establish |
|---|---|---|
| Source reviewed | The cited adapter and relevant owner branches were inspected | Successful execution for every actor, input, or deployment |
| Existing test assertions inspected | The cited test expresses a specific expected outcome | A fresh passing test result or full coverage of the use case |
| Historical result cited | A dated handoff recorded a result | That the changing worktree still has the same behavior |
| Runtime observed | The recorded actor, path, interaction, and response were actually observed | Unexercised writes, replay, races, external delivery, or physical evidence |
| Blocked or unverified | A prerequisite, authority conflict, or evidence boundary remains explicit | Permission to invent a successful path |

The [runtime observation log](../../../openspec/changes/action-core-authority-realignment/use-case-audit/RUNTIME-OBSERVATIONS.md) records entry-page navigation, permission denials, and a public browse-to-application handoff. No application was submitted during that handoff. No seeder, reset, workflow mutation, worker drain, provider request, paired media call, printer operation, or physical scan was intentionally executed by this audit. Read-side session, audit, notification seen-state, or cache effects may occur; an unchanged database is not claimed.

A registered route is not a use case, and a mapped route is not a passing scenario. The [route snapshot](../../../openspec/changes/action-core-authority-realignment/use-case-audit/route-snapshot.json) records the initial capture and the later added endpoint without renumbering earlier identifiers. Standalone public HTML files and discovered background callers are listed separately from that HTTP denominator. Dependencies and callers not fully inspected remain named in the domain registers.

## Unresolved validation and contract boundaries

The register records source-derived concerns and missing proof rather than treating the existing demonstration as acceptance:

- Identity linking and MFA-reset session revocation require the identity owner's contract review.
- Bulk examination booking updates, Direct Assessment authority and replay, and accommodation scope require examination-owner reconciliation.
- Current result artifacts, print attestation, release, and generated-job timestamps must not be treated as interchangeable evidence.
- Direct partial imports have recorded failed-row recovery limits; interoffice continuation is a separate retryable workflow.
- Consultation scope, referral readiness, protected field projections, and live online media need distinct verification.
- Quota edits must be reconciled with every reservation and selection writer; advisory model output does not make a placement decision.
- Dashboard sample/static values are not institutional measurements or research results.
- Notification outcome-unknown handling differs from terminal retry, and the corrected notice-document bridge has not acquired full acceptance evidence.
- Physical OMR qualification and activation require genuine printer/device provenance; synthetic software fixtures do not satisfy that gate.
- Public development references and diagnostic tools must be distinguished from the authorized staff manual and restricted demo operations.

Exact identifiers, source citations, scope limits, and owning handoffs are retained in the [coverage register](../../../openspec/changes/action-core-authority-realignment/use-case-audit/COVERAGE-REGISTER.md). A finding is not a demonstrated exploit, and missing execution evidence is not automatically a defect.

## Use in the manuscript and system manual

This appendix supports the software construction and engineering-verification discussion. It supplies traceable control-flow illustrations and a reproducible list of validation boundaries. It must not be used to fill missing TAM responses, claim institutional deployment acceptance, infer physical device accuracy, or report sample dashboard values as empirical data.

For the proposed `/demo` manual, the engineering register is the source for reviewed instructions, role-aware entry links, prerequisites, expected outcomes, and blocked-state guidance. Credentials, seeding/reset commands, internal diagnostics, clinical fields, answer keys, and unresolved policy proposals do not belong in the ordinary tester view. Existing contextual page guides can reference the same identifiers. Module retirement and document cleanup remain a separate change after useful content and research provenance have been accounted for.
