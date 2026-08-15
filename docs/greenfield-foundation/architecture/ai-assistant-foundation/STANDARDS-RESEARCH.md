---
name: NEXIAM AI Assistant Foundation — Standards Research
type: research-baseline
status: reviewed-baseline
date: 2026-08-09
scope: contracts, proposal integrity, AI safety, framework adapter, and knowledge retrieval
---

# Standards and technology research baseline

This document records the standards that shape the architecture. They are guardrails and interoperability contracts; they do not replace NEXIAM's domain rules, authorization, or confirmation path.

## Adopt as architectural baselines

| Area | Baseline | NEXIAM consequence |
|---|---|---|
| Structured contracts | [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12) | Use a documented NEXIAM-supported subset for capability inputs, typed outputs, proposals, stream events, and UI intents. Reject unknown fields and implicit coercion. |
| Exact proposal identity | [RFC 8785 JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785.html) | Canonicalize normalized proposal JSON before hashing; bind the hash to actor, scope, command/version, target versions, rule-set version, impact, expiry, and idempotency identity. |
| AI application security | [OWASP Top 10 for LLM Applications](https://owasp.org/www-project-top-10-for-large-language-model-applications/) and [2025 Excessive Agency guidance](https://owasp.org/www-project-top-10-for-large-language-model-applications/2_0_vulns/LLM06_ExcessiveAgency.html) | Minimize functionality/permissions/autonomy, complete authorization in downstream code, validate outputs, require human approval for consequential work, and monitor tool activity. |
| AI lifecycle risk | [NIST AI RMF](https://www.nist.gov/itl/ai-risk-management-framework) and [Generative AI Profile NIST-AI-600-1](https://nvlpubs.nist.gov/nistpubs/ai/NIST.AI.600-1.pdf) | Maintain evidence across Govern, Map, Measure, and Manage: owners, intended use, privacy, evaluation thresholds, incidents, rollback, and ongoing re-evaluation. |
| Framework transport | [Laravel AI SDK documentation](https://laravel.com/docs/13.x/ai-sdk) and [official v0.10.3 release](https://github.com/laravel/ai/releases/tag/v0.10.3) | Treat `laravel/ai` as an adapter candidate for agents, tools, structured output, streaming, approvals, and fakes. No SDK type or approval decision crosses into domain authority. |
| MCP inspiration | [MCP authorization specification](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization) | Borrow the named-tool/schema/authorization vocabulary, but do not expose an MCP server or treat transport authorization as NEXIAM domain authorization. |

## Pin before coding

The standards names alone are not sufficient for independent implementations. AI-001A/AI-006 must record:

- the exact JSON Schema validator/library, Draft 2020-12 dialect, supported vocabulary/keywords, nullability/default/coercion behavior, unknown-field behavior, and stable error-path format;
- the exact RFC 8785 implementation and fixtures, including duplicate-key rejection, I-JSON/number handling, Unicode preservation, and domain-separated hash construction;
- the versioned `RuleDependencySet`/`ConstraintEvidence` schema and compatibility policy;
- the provider adapter's normalized error/usage/stream/cancellation fixtures.

## The authority mapping

```text
domain Actions/Queries/rules  = business truth
Capability Registry            = versioned adapter contract
RuleSetRef + ConstraintEvidence = proposal validity evidence
PromptSpec/rule digest         = model guidance projection
model output                   = untrusted candidate
proposal record                = immutable snapshot
confirmation + domain preflight = final authority at execution time
RulePublication + KnowledgeRouter = atomic rule/source publication and provider-bound retrieval enforcement
```

## Laravel AI SDK fit

The official Laravel 13 documentation lists agents with instructions, conversation context, tools, structured output, streaming, queue/broadcast options, human tool approval, embeddings, reranking, provider failover, and testing fakes. Those are useful transport facilities. They do not decide actor ownership, page-pack relevance, NEXIAM capability scope, proposal hashing, rule validity, provider data policy, durable usage settlement, or domain command execution.

The package remains uninstalled and ADR-gated. Before installation, the live spike must verify the exact pinned release, provider/model behavior, stream and cancellation semantics, structured-output failure modes, usage accuracy, privacy/retention handling, and degraded behavior.

## RAG and knowledge research conclusion

RAG is not active in the current system. The architecture has a deferred `KnowledgeIndexPort` boundary only.

The later RAG OpenSpec must implement one source registry with:

- owner and approver;
- audience, institution, and purpose;
- effective, superseded, and revoked versions;
- checksum, extraction/index state, and deletion evidence;
- citation ID and canonical source link;
- selected retrieval route (`policy_index` or deterministic `application_query`);
- embedding model/version only for approved policy sources.

Approved institutional terms, conditions, rules, and guidance may enter the policy index. Schedules, rosters, applicant records, and staff operational state remain deterministic authorized Queries/projections. They must not be embedded just because they are visible on a page.

## Research gates still open

1. Provider data-processing, retention/ZDR, region, and contract terms.
2. Exact Laravel AI SDK/provider cancellation and post-timeout billing behavior.
3. Embedding model, dimension, index, backup/restore, revocation, and re-index strategy.
4. Accepted thresholds for proposal validity, unsupported-claim rate, citation support, refusal precision/recall, leakage, and prompt-injection tests.
5. Rule-set governance: who owns each domain rule, how versions are published, and which changes invalidate proposals or skills.

No live provider, embedding store, prompt sanitizer, or external MCP connector is justified until the applicable ADR and OpenSpec close these gates.
