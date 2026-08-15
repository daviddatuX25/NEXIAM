# Change Proposal: Nexi Applicant Inquiry

**Change ID:** `nexi-applicant-inquiry`  
**Status:** ACTIVE  
**Phase:** E3 OpenSpec 7  
**Depends on:** `nexi-policy-inquiry-rag` archived PASS 2026-08-10  
**Archived prior:** `openspec/changes/archive/2026-08-10-nexi-policy-inquiry-rag/`

## Intent

Mount **live applicant Inquiry** on the applicant `SurfaceProfile`: approved institutional policy (audience-scoped) plus **lifecycle-authorized own-data** projections (result/score fields release-gated). Conversations stay partitioned; no staff tools; no applicant mutation Assistant.

## In scope

1. Applicant turns on `/nexi/*` with `actor_type=applicant` (fix OpenSpec 3 staff-only gate)
2. Policy retrieve with applicant audiences (`staff_and_applicant_inquiry`, `applicant_inquiry`) — never `staff_only`
3. Own-data projector (attempt status / schedule / released results only)
4. Conversation isolation + clear/segment; cross-applicant snapshot denial
5. Provider-down / emergency-stop fail-closed; FakeAiGateway own-data replies
6. Pest: policy cite, staff_only deny, own-status, unreleased score deny, cross-applicant deny, clear

## Non-goals

| Deferred | Next |
|---|---|
| Applicant Assistant mutations (reserve/correct) | later applicant Assistant |
| Exam-session staff Apply | `nexi-exam-session-assistant` |
| pgvector embeddings | later ADR |
| Staff tool packs on applicant shell | never |

## Exit

Applicant asks published policy → cite; asks own status → projection answer; unreleased scores refused; other applicant’s conversation → 403; no Apply/tools.
