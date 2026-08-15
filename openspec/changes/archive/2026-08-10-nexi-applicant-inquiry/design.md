# Design: Nexi Applicant Inquiry

## Decisions

1. **Actor typing** — `TurnSubmitter` derives `applicant` from surface key + `ApplicantAccount`, not “User ⇒ staff”.
2. **Own-data** — `ApplicantOwnDataProjector` is the authorized Query (mirrors portal schedule projection). ProcessNexiRun attaches it for applicant runs; Kernel skill registration of `applicant.explain_status@1` deferred until applicant GF-21 portal caps exist.
3. **Policy** — reuse `KnowledgeRouter` with applicant audiences; title+body lexical index (OS6).
4. **Clear** — `POST /nexi/conversations/clear` segments open conversation via `ContextEpochService::segment`.
5. **Emergency stop** — staff-only; applicants get fail-closed on turns when stop active.

## Failure

| Case | Behavior |
|---|---|
| No policy + no relevant own-data | refusal / no-support copy |
| Unreleased results | `results_unreleased` in projection; Fake refuses scores |
| Cross-actor snapshot | 403 |
| Provider down | existing `provider_unavailable` fail path |
