# Independent AI software review: `two-item-count-native-v1`

**Reviewer:** `KitNativeFixtureReview` (AI software reviewer; `openai-codex/gpt-6.1-sol`)
**Review date:** 2026-10-08
**Decision:** Accepted for the exact synthetic literal response vector, independently hand-worked count arithmetic, and bounded source-level native-label compatibility described below.
**Reviewer reference:** `tests/Fixtures/TestKit/two-item-count-native-v1.review.md`

This is an actual independent, read-only AI software review. The reviewer is not a human supervisor, institution, test publisher, or source-rights authority. No institutional approval, physical-device/printer qualification, runtime success, detection/worker certification, activation approval, norm conversion, or end-to-end bundle acceptance is claimed.

## Exact newly reviewed source

- Source: `tests/Fixtures/TestKit/two-item-count-native-v1.json`.
- Actual source SHA-256: `5d3e30c40c747380bc64dfd8df0fcfbcd5ed0f141a43e1f8930c2c492438912b`.
- The reviewer read the complete 65-line literal source and independently computed SHA-256 with `sha256sum` against the actual file bytes. The source worker `KitNativeFixture` identified these bytes as its newly written, byte-stable source. This is AI-worker/repository software-fixture provenance, not human or institutional authorship.
- This approval binds only these exact bytes. Any source change requires fresh hash-bound independent review; the earlier `two-item-count-v1` review is not transferred or relabeled.

## Exact synthetic procedure

One page contains two scored fixed-order items: `LOGIC_Q_1` has fixed Set A key `A`, and `LOGIC_Q_2` has fixed Set A key `B`. Each item has ordered option tokens `A`, `B`; explicit `null` represents confirmed blank. `RAW_COUNT` is a required `correct_item_count` metric with unit `count`, integer precision 0, and bounds 0 through 2. Each exact key match contributes one; an incorrect token or confirmed blank contributes zero. There are no penalties, weights, norms, practice items, composites, or recorded measures. This is a synthetic software procedure, not an official publisher or institutional procedure.

## Independently hand-worked outputs

The expected values were hand-worked by direct comparison to the stated fixed key, not obtained from the evaluator or detector.

|Case|Literal responses|Hand-work|Expected `RAW_COUNT`|
|---|---|---:|---:|
|`correct`|`LOGIC_Q_1=A`, `LOGIC_Q_2=B`|1 + 1|`2`|
|`incorrect`|`LOGIC_Q_1=B`, `LOGIC_Q_2=A`|0 + 0|`0`|
|`blank`|`LOGIC_Q_1=null`, `LOGIC_Q_2=null`|0 + 0|`0`|
|`mixed`|`LOGIC_Q_1=A`, `LOGIC_Q_2=A`|1 + 0|`1`|

All four case identifiers are distinct. Every case contains exactly page 1 and both native labels exactly once, with the `token` key explicitly present. Both `A` and `B` are exercised at each individual native label, and explicit null blanks are exercised at both labels. The declared correct/incorrect/blank coverage and mixed correct+incorrect coverage agree with the actual response vectors. Each case declares exactly one literal decimal-string `RAW_COUNT` output and an empty recorded-measures list, appropriate only for this count-only procedure.

## Producer and consumer compatibility inspected

The owning OpenSpec is `versioned-test-kit-bundle`; its independently authored/frozen source and software-versus-institutional evidence boundaries remain intact.

`OmrMappingValidator::nativeLabels()` already accepts explicit `PREFIX_Q_n` labels. Its explicit native-label branch preserves `LOGIC_Q_1` and `LOGIC_Q_2` exactly even when the block is named `COUNT_ITEMS`; the block-name fallback is not used for these labels. The native-label/default behavior was not changed for this review. `validate()` carries the normalized values into `fieldLabels`.

`OmrTemplateGeometryProjector::deriveProjection()` accepts both labels through its existing native-field regex, yielding physical catalog code `LOGIC` and contiguous question positions 1 and 2. `persistProjection()` resolves that code against the active catalog by exact code and fails if absent; it does not choose an arbitrary catalog row. This is physical geometry metadata, not raw-scoring authority.

The inspected `tests/Support/TestKitHelpers.php` selects/declares catalog code `LOGIC` explicitly, uses a separate profile subtest `COUNT-<suffix>`, sets `RAW_COUNT.construct_aptitude_area_id` to the actual LOGIC catalog ID before `SaveTestInstrumentDraft`, and calls `ApproveTestInstrumentVersion::approveDemoVersion`. Its fixture key is the explicit closed map `LOGIC_Q_1=A` / `LOGIC_Q_2=B`; explicit local numbers 1/2 select those exact labels, and emitted Set A key rows carry exact profile item IDs. No native-prefix-derived scoring authority is used. The helper explicitly declares `RAW_COUNT` bounds 0 through 2 and integer precision 0; `TestInstrumentProfileValidator` also derives those integer count bounds from the exact two scored items.

`ExactTestKitItemMap` validates exact page/native destinations, profile item and subtest IDs, ordered tokens, and fixed Set A keys. `TestKitAcceptancePlan` resolves expected metric codes to the exact profile metric IDs and validates complete page/label/token coverage before freezing. `AssessmentRawEvaluator` indexes exact item IDs, subtest IDs, and metric IDs; the correct-item-count dispatch counts exact non-null key matches by subtest ID. No native-prefix or name-based scoring fallback is used in the inspected path.

These are read-only source-level compatibility findings, not proof that the helper was executed or that persisted materials currently exist/match. Before any detection, the production resolver must bind this source to exact matching one-page/two-item material and freeze the plan/protocol. Mismatched keys, labels, pages, tokens, profile rules, bounds, or metrics must be rejected; no late reinterpretation is authorized.

## Actual canonical protocol identity

- Schema: `test-kit-acceptance-protocol-v1`.
- SHA-256: `e2be57fa029e14e3b3660cd7197d3159072abbc325de139ecb1e2d43cb2127c5`.
- Canonical UTF-8 JSON bytes, with no trailing newline:

`{"blank_representation":null,"case_identity":"case_id","case_limit":128,"coverage_required":["correct","incorrect","blank"],"page_identity":"page_no","recorded_measure_procedure_match":"exact_metric_procedure_reference","require_all_supported_rules":["correct_item_count","recorded_measure","sum_raw_metrics"],"require_every_option_token":true,"required_metric_outputs":"independently_authored_decimal_by_metric_code","response_identity":["page_no","native_label"],"schema_version":1}`

The reviewer inspected the actual `TestKitAcceptancePlan::protocolRules()` values, referenced constants, recursive lexical object-key canonicalization, preserved array ordering, and JSON flags. An independent Python standard-library fact calculation rendered the corresponding compact canonical bytes and computed the digest above. No application or evaluator was loaded or executed. Generic protocol support for recorded measures or composite metrics does not broaden this fixture's count-only scope.

## Retained evidence and limitations

The original `two-item-count-v1.json` and its review remain separate bounded arithmetic evidence and were not edited by this reviewer. Observed original source SHA-256: `de0f2a7bc1199fc4eea5c90a3c864c61de546409a1fa4f1984c6c4148670cc33`; original review Markdown SHA-256: `85e607c31f8f0e21118f52c6a710fd242997d7b390153d643f7d5cf9577533b6`; original review JSON SHA-256: `7a1c648685e8f277d3a6b14cdc68f2cf31a16531ef250ebd72b1226116f6ea3d`.

This review accepts only the exact new literal vector, its independent arithmetic, and the inspected existing source-level label route. It does not establish wider multi-page, repeated-local-number, practice, custom-token, weighted, composite, recorded-measure, capture, retake, concurrency, or institutional acceptance. No tests, checks, formatters, migrations, seed/reset commands, evaluator/application execution, or database writes were performed by the reviewer. No receipt, Active status, human approval, institutional source rights, or physical qualification was created. The authoring worker must persist this actual report and sidecar, then compute their actual hashes for registration; invented report hashes are not authorized.
