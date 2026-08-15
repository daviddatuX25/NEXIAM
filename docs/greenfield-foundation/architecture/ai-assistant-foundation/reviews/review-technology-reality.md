# Technology / Standards Reality Review — AI Assistant Foundation

**Reviewed:** 2026-08-09  
**Artifacts:** `ARCHITECTURE-SPINE.md`, `STANDARDS-RESEARCH.md`  
**Verdict:** **PASS — the named standards and framework capabilities fit the architecture, no technology choice is prematurely promoted, and the remaining validator/canonicalization/adapter details are now explicit pre-coding gates.**

## Repository and Laravel AI reality

The committed runtime remains compatible with the reviewed Laravel AI candidate: `system/composer.json` requires PHP `^8.4` and Laravel `^13.23`, while the lockfile resolves Laravel `v13.24.0`. The official `laravel/ai` v0.10.3 package requires PHP `^8.3` and Illuminate `^12.0|^13.0` (including `illuminate/json-schema` `^12.62|^13.15`). The package remains absent from the application manifest and lockfile, so the documents correctly call it an **uninstalled, ADR-gated adapter candidate** rather than installed architecture.

The adapter boundary is appropriate. Laravel AI documents agents, JSON-schema-shaped tools and structured output, SSE/Vercel-protocol streaming, queue/broadcast operation, persisted human tool approval, embeddings/reranking, failover, and test fakes. It does not supply NEXIAM actor ownership, domain authorization, proposal validity, usage accounting, privacy policy, or command authority. AD-13 correctly keeps SDK types behind NEXIAM DTOs and requires adapter conformance fixtures. SDK tool schemas must remain model-facing descriptions; the Capability Kernel's independent validation remains authoritative.

Sources: [Laravel AI v0.10.3 manifest](https://github.com/laravel/ai/blob/v0.10.3/composer.json), [Laravel 13 AI SDK documentation](https://laravel.com/docs/13.x/ai-sdk), [repository Composer manifest](../../../../../system/composer.json), and [repository lockfile](../../../../../system/composer.lock).

## Standards findings

### JSON Schema Draft 2020-12 — correct baseline; exact profile still must be pinned

AD-2 and the standards baseline correctly say **NEXIAM-supported subset**, rather than claiming every Draft 2020-12 feature. Strict unknown-field rejection and no implicit coercion/default insertion are valid NEXIAM rules, but they are not automatic consequences of naming Draft 2020-12:

- `default` is an annotation, not an instruction to mutate an instance;
- `format` is annotation-only by default unless the Format-Assertion vocabulary/validator behavior is explicitly enabled;
- `unevaluatedProperties` depends on annotation results from adjacent applicators and is more complex than blanket `additionalProperties: false`;
- unknown schema keywords may be treated as annotations, so schemas themselves need meta-schema/profile validation.

The new **Pin before coding** gate now requires AI-001A/AI-006 to record the exact validator/library, Draft 2020-12 dialect, supported vocabulary/keywords, nullability/default/coercion and unknown-field behavior, and stable error paths. Together with AD-2's mandatory compatibility fixtures, this satisfies the standards condition. The resulting ADR should include the object-closure, `format`, numeric-limit, and reference-policy choices within that recorded profile.

Sources: [JSON Schema 2020-12 Core](https://json-schema.org/draft/2020-12/json-schema-core), [Validation vocabulary](https://json-schema.org/draft/2020-12/json-schema-validation), and [Format vocabularies](https://json-schema.org/draft/2020-12/json-schema-validation#name-vocabularies-for-semantic-c).

### RFC 8785 — appropriate for proposal identity; constrain the input profile

RFC 8785 is a suitable exact-byte basis for proposal hashes, and AD-7 correctly combines it with a domain-separated SHA-256 digest over the security-relevant proposal fields. JCS does not define NEXIAM's domain normalization. It requires I-JSON-compatible input: no duplicate object names, valid Unicode, and numbers representable as IEEE-754 doubles; it uses ECMAScript number serialization and deliberately does not normalize Unicode strings.

The new gate now requires AI-001A/AI-006 to pin the exact JCS implementation and fixtures, including duplicate-key rejection, I-JSON/number handling, Unicode preservation, and domain-separated hash construction. AD-7 additionally binds the security-relevant fields and requires canonical/preflight evidence. This satisfies the reproducibility condition; the fixtures should exercise invalid Unicode/NaN/Infinity, high-precision decimals or money, large identifiers/version tokens, and cross-runtime byte equality.

Source: [RFC 8785 JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785.html), especially Sections 3.1–3.2 and its number-serialization test vectors.

### OWASP and NIST — mappings are accurate and do not claim certification

The OWASP mapping is direct: granular tools, minimum functionality and permissions, downstream authorization, output validation, constrained autonomy, monitoring, and human approval for high-impact actions match the Excessive Agency guidance. The architecture goes further by withholding executable commands from provider-facing tools and dispatching only through a fresh confirmation path.

The NIST mapping is also accurate. NIST AI RMF and NIST AI 600-1 organize voluntary risk work across Govern, Map, Measure, and Manage; the architecture's named owners, intended-use boundaries, privacy decisions, pre-deployment evaluation, ongoing monitoring, incident handling, rollback/degradation, and re-evaluation are reasonable project-level evidence. The documents correctly use NIST as a risk-management baseline and make no compliance or certification claim.

Sources: [OWASP LLM06 Excessive Agency](https://owasp.org/www-project-top-10-for-large-language-model-applications/2_0_vulns/LLM06_ExcessiveAgency.html), [NIST AI RMF](https://www.nist.gov/itl/ai-risk-management-framework), and [NIST AI 600-1 Generative AI Profile](https://nvlpubs.nist.gov/nistpubs/ai/NIST.AI.600-1.pdf).

## Knowledge-source and RAG gate

**Pass.** No active RAG, embedding store, pgvector dependency, or provider-hosted vector store is asserted. AD-12/AD-21 and `STANDARDS-RESEARCH.md` establish only a deferred `KnowledgeIndexPort` and a source-governance contract. They correctly require authorization/institution/audience/effective/revocation predicates during candidate retrieval before chunks reach caches or rerankers, typed citation snapshots, source lifecycle evidence, and refusal when support is absent.

The split is appropriately conservative:

- approved, owner-controlled institutional policy may later be indexed;
- applicant, roster, schedule, and staff operational data remain deterministic authorized Queries/projections;
- retrieved text is evidence, never instructions or authority;
- provider terms, embedding model/dimension, index choice, backup/restore, deletion/re-index, retrieval quality, citation support, refusal, leakage, and injection thresholds remain explicit ADR/OpenSpec gates.

This also aligns with NIST AI 600-1 recommendations to document data/content lineage, evaluate RAG grounding, and verify sources and citations during pre-deployment and ongoing measurement.

## Prior technology corrections

All four first-pass corrections are resolved: v0.10.3 is current and marked not installed/ADR-gated; the testing claim is narrowed to documented fakes/assertions; and provider-request cancellation is explicitly a spike result rather than a promised SDK feature.

## Approval condition

The architecture and standards baseline may proceed with a **PASS**. The exact JSON Schema, RFC 8785, rule-evidence, and provider-adapter details are now correctly delegated to blocking AI-001A/AI-006 records and conformance fixtures. Retain the existing AI-002/AI-003/AI-007 gates before SDK, provider, or RAG installation.
