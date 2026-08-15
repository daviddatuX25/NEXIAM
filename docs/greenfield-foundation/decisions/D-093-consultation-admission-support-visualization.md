# D-093 — Consultation-embedded admission-support visualization and deferred engines

**Status:** APPROVED — founder direction recorded 2026-08-15
**OpenSpec:** `2026-08-15-admission-model-program-head`
**Extends:** GF-10 §§5–8; GF-22 chart and consultation contracts

## Decision

### Consultation owns the staff workspace

The per-applicant admission-support interface is part of the existing consultation workspace. It is not a separate suggestion page or chatbot. Guidance/Test Administrator uses the selected applicant's released assessment facts, application preferences, structured consultation evidence, analysis outputs when available, and human decision/pool history in one tabbed workspace.

The current implementation cut SHALL build the useful backbone before either analysis engine:

- applicant-specific overview and readiness/state facts;
- released converted per-domain score profile shown as accessible charts plus the same-value table;
- ordered application preferences and approved structured consultation/interest evidence;
- separate analysis-output regions that preserve the source/engine/version of every result and never combine independent signals into one synthetic score;
- human-owned consultation decision, program pool/advised default, follow-up, and history panels;
- loading, result-not-ready, no-analysis, stale-input, unavailable, invalid-output, and denied states without fabricated findings.

Charts are lookup/decision-support views only. They do not calculate new business truth in the browser, mutate state, infer admission, or replace the counselor's decision. Every chart uses the GF-22 typed chart contract and an accessible table alternative.

Authorized Program Head Tier B may receive a bounded read-only projection of the same admission-support evidence for the assigned program when D-080 relationship rules pass. This reuse does not give Program Head ordinary consultation/advising ownership, cross-program output, or application acceptance authority.

### Two future admission-support plugins remain separate and deferred

1. **YuKee artifact/K-Means compatibility plugin.** This consumes the verified supplied artifact and engine specification package and reproduces its attributable cluster/profile outputs and visualization contract. It must satisfy GF-10 Path A before activation; NEXIAM must not call a rules approximation “YuKee.”
2. **Transparent deterministic/rule-based plugin.** This is a separately named, versioned, expert-reviewed algorithm/rule profile under GF-10 Path B. Its final algorithm name, inputs, thresholds, mappings, evaluation, and output contract require a later dedicated planning change.

Neither plugin, adapter, provider SDK, fake fixture engine, `ProgramSuggestion` review workflow, nor predictive claim is implemented by the current change. BGE/interest embedding remains a separate future research option and is not silently treated as either of these two plugins.

The current cut may define only the provider-neutral visualization/input boundary needed by the consultation backbone. Later plugin changes must preserve independent evidence objects and human authority; an engine output never writes consultation decisions, program pools/defaults, applicant selections, quota, follow-up, release, or enrollment state.

## Consequences

- Remove the demonstration deterministic suggestion provider from the queued implementation plan.
- Replace the standalone suggestion panel with consultation tabs and real released-score/application/consultation evidence visualizations.
- Keep manual consultation and pool/default operations complete when no plugin is installed.
- Plan and evidence the YuKee and deterministic plugins later as separate integrations against the shared visualization boundary.
