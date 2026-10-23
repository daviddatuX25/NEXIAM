<?php

namespace App\Assessment\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Validates and freezes independently authored Test Kit acceptance vectors. */
final class TestKitAcceptancePlan
{
    public const PLAN_SCHEMA_VERSION = 1;

    public const PLAN_SCHEMA_ID = 'test-kit-acceptance-plan-v1';

    public const PROTOCOL_SCHEMA_VERSION = 'test-kit-acceptance-protocol-v1';

    private const PROTOCOL_RULES_SCHEMA_VERSION = 1;

    private const MAX_ARTIFACT_BYTES = 1_048_576;

    private const MAX_PAGES = 4;

    private const MAX_CASES = 128;

    private const MAX_CASE_ID_LENGTH = 100;

    private const MAX_TRUTH_REFERENCE_LENGTH = 500;

    private const SUPPORTED_RULES = ['correct_item_count', 'recorded_measure', 'sum_raw_metrics', 'formula_raw_metrics'];

    /**
     * @param  array<string, mixed>  $authoredPlan
     * @return array{acceptance_plan_json:array<string,mixed>,acceptance_plan_schema_version:string,acceptance_plan_sha256:string,acceptance_protocol_schema_version:string,acceptance_protocol_sha256:string,registry_sha256:string,expected_worker_identity:array<string,string>,scoring_kernel_identity:string}
     */
    public function freeze(TestKitVersion $version, array $authoredPlan): array
    {
        $this->assertPlanShape($authoredPlan);
        $this->assertAttribution($authoredPlan['truth_source']);
        $sourceEvidence = isset($authoredPlan['source_evidence'])
            ? $this->canonicalSourceEvidence($authoredPlan['source_evidence'])
            : null;
        $template = $version->templateVersion;
        $booklet = $version->bookletVersion;
        $set = $version->bookletSet;
        $profile = $version->testInstrumentVersion;
        if ($template === null || $booklet === null || $set === null || $profile === null
            || (int) $template->getKey() !== (int) $version->template_version_id
            || (int) $booklet->getKey() !== (int) $version->booklet_version_id
            || (int) $set->getKey() !== (int) $version->booklet_set_id
            || (int) $profile->getKey() !== (int) $version->test_instrument_version_id) {
            throw new RuntimeException('The Test Kit version has incomplete or mismatched component pins.');
        }

        $contract = app(KitCompositionContext::class)->resolveVersion($version)['item_map'];
        $this->assertSupportedMetrics($contract['metrics']);
        $cases = $this->canonicalCases($authoredPlan['cases'], $contract, (int) $template->page_count);
        $this->assertCoverage($cases, $contract);
        $protocolIdentity = $this->protocolIdentity();
        $workerIdentity = $this->expectedWorkerIdentity();

        $itemBindings = array_map(static function (array $binding): array {
            unset($binding['correct_token']);

            return $binding;
        }, $contract['bindings']);
        $snapshot = [
            'schema_version' => self::PLAN_SCHEMA_VERSION,
            'plan_schema_id' => self::PLAN_SCHEMA_ID,
            'test_kit_version_id' => (int) $version->getKey(),
            'manifest_sha256' => $this->sha256($version->manifest_sha256, 'Test Kit manifest hash'),
            'profile_content_sha256' => $this->sha256($contract['content_sha256'], 'Profile content hash'),
            'item_bindings' => $itemBindings,
            'metrics' => $contract['metrics'],
            'truth_source' => $authoredPlan['truth_source'],
            'cases' => $cases,
            'protocol_identity' => $protocolIdentity,
            'expected_worker_identity' => $workerIdentity,
            'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
        ];
        if ($sourceEvidence !== null) {
            $snapshot['source_evidence'] = $sourceEvidence;
        }
        $canonicalSnapshot = $this->canonicalize($snapshot);

        return [
            'acceptance_plan_json' => $canonicalSnapshot,
            'acceptance_plan_schema_version' => self::PLAN_SCHEMA_ID,
            'acceptance_plan_sha256' => hash('sha256', $this->json($canonicalSnapshot)),
            'acceptance_protocol_schema_version' => $protocolIdentity['schema_version'],
            'acceptance_protocol_sha256' => $protocolIdentity['sha256'],
            'registry_sha256' => $sourceEvidence['registry_sha256'] ?? '',
            'expected_worker_identity' => $workerIdentity,
            'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
        ];
    }

    /** @return array{schema_version:string,sha256:string} */
    /** @var ?array{schema_version:string,sha256:string} */
    public static ?array $protocolOverride = null;

    /** @return array{schema_version:string,sha256:string} */
    public function protocolIdentity(): array
    {
        if (self::$protocolOverride !== null) {
            return self::$protocolOverride;
        }

        $rules = $this->protocolRules();

        return [
            'schema_version' => self::PROTOCOL_SCHEMA_VERSION,
            'sha256' => hash('sha256', $this->json($this->canonicalize($rules))),
        ];
    }

    /**
     * Resolve a deliberately selected, protected plan record for a fresh run.
     * The caller supplies the immutable plan id; no default/first/latest plan is selected.
     *
     * @return array{acceptance_plan_json:array<string,mixed>,acceptance_plan_schema_version:string,acceptance_plan_sha256:string,acceptance_protocol_schema_version:string,acceptance_protocol_sha256:string,registry_sha256:string,expected_worker_identity:array<string,string>,scoring_kernel_identity:string}
     */
    public function resolveForStart(User $actor, TestKitVersion $version, int $explicitPlanId): array
    {
        return DB::transaction(function () use ($actor, $version, $explicitPlanId): array {
            $kit = $version->testKit()->lockForUpdate()->firstOrFail();
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! app(CapabilityAuthorizer::class)->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $lockedVersion = TestKitVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();
            $record = TestKitAcceptancePlanRecord::query()
                ->where('test_kit_version_id', $lockedVersion->getKey())
                ->whereKey($explicitPlanId)
                ->lockForUpdate()
                ->firstOrFail();
            $sourceKey = $record->source_key;
            if (! is_string($sourceKey)) {
                throw new RuntimeException('The reviewed Test Kit acceptance source is not attributable.');
            }
            $source = $this->loadReviewedSource($sourceKey);
            $frozen = $this->freeze($lockedVersion, [
                ...$source['authored_plan'],
                'truth_source' => $source['truth_source'],
                'source_evidence' => $source['source_evidence'],
            ]);
            if ($record->plan_schema_version !== $frozen['acceptance_plan_schema_version']
                || $record->protocol_schema_version !== $frozen['acceptance_protocol_schema_version']
                || ! hash_equals((string) $record->plan_sha256, $frozen['acceptance_plan_sha256'])
                || ! hash_equals((string) $record->protocol_sha256, $frozen['acceptance_protocol_sha256'])
                || ! hash_equals((string) $record->manifest_sha256, (string) $lockedVersion->manifest_sha256)
                || $record->plan_payload !== $frozen['acceptance_plan_json']
                || ! is_array($record->truth_source_references_json)
                || $record->truth_source_references_json !== $source['truth_source_references']
                || ! hash_equals((string) $record->authored_source_sha256, $source['source_sha256'])
                || $record->author_reference !== $source['author_reference']
                || $record->provenance_kind !== $source['provenance_kind']
                || $record->review_evidence_reference !== $source['review_evidence_reference']
                || ! hash_equals((string) $record->review_evidence_sha256, $source['review_evidence_sha256'])) {
                throw new RuntimeException('The reviewed Test Kit acceptance plan or its evidence references are stale or incomplete.');
            }

            return $frozen;
        });
    }

    /**
     * Load only a configured checked-in source and review artifact. Caller input is
     * a source key, never a path or an attestation.
     *
     * @return array{authored_plan:array<string,mixed>,truth_source:array{reference:string,sha256:string,attributed_to:string},source_key:string,source_sha256:string,author_reference:string,review_evidence_reference:string,review_evidence_sha256:string,reviewer_reference:string,reviewer_evidence_sha256:string,provenance_kind:string,source_evidence:array<string,mixed>,truth_source_references:list<array{reference:string,sha256:string}>}
     */
    public function loadReviewedSource(string $sourceKey): array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $sourceKey)) {
            throw new RuntimeException('The configured acceptance source key is invalid.');
        }
        $registry = config('test-kit.acceptance_sources');
        if (! is_array($registry) || $registry === []) {
            throw new RuntimeException('The reviewed acceptance-source registry is unavailable.');
        }
        $entry = $registry[$sourceKey] ?? null;
        if (! is_array($entry) || ! in_array($entry['provenance_kind'] ?? null, ['synthetic_software_fixture', 'reviewed_software_plan'], true)) {
            throw new RuntimeException('No independently authored and reviewed acceptance plan is configured for this source key.');
        }
        $registrySha256 = hash('sha256', $this->json($this->canonicalize($registry)));

        $fixtureRoot = base_path('tests/Fixtures/TestKit');
        $sourcePath = $fixtureRoot.'/'.$sourceKey.'.json';
        $reviewPath = $fixtureRoot.'/'.$sourceKey.'.review.json';
        $reviewerEvidencePath = $fixtureRoot.'/'.$sourceKey.'.review.md';
        $sourceBytes = $this->readCheckedInArtifact($fixtureRoot, $sourcePath);
        $reviewBytes = $this->readCheckedInArtifact($fixtureRoot, $reviewPath);
        $reviewerEvidenceBytes = $this->readCheckedInArtifact($fixtureRoot, $reviewerEvidencePath);
        $sourceSha256 = hash('sha256', $sourceBytes);
        $reviewSha256 = hash('sha256', $reviewBytes);
        $reviewerEvidenceSha256 = hash('sha256', $reviewerEvidenceBytes);
        if (! is_string($entry['source_sha256'] ?? null) || ! hash_equals($entry['source_sha256'], $sourceSha256)
            || ! is_string($entry['review_evidence_sha256'] ?? null) || ! hash_equals($entry['review_evidence_sha256'], $reviewSha256)
            || ! is_string($entry['reviewer_evidence_sha256'] ?? null) || ! hash_equals($entry['reviewer_evidence_sha256'], $reviewerEvidenceSha256)) {
            throw new RuntimeException('The configured acceptance source or review evidence changed after approval.');
        }
        $authoredPlan = json_decode($sourceBytes, true, 512, JSON_THROW_ON_ERROR);
        $review = json_decode($reviewBytes, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($authoredPlan) || ! is_array($review)) {
            throw new RuntimeException('The acceptance source and review artifacts must contain JSON objects.');
        }

        $protocol = $this->protocolIdentity();
        $this->exactKeys($review, [
            'schema_version', 'source_sha256', 'acceptance_protocol_schema_version',
            'acceptance_protocol_sha256', 'reviewer_reference', 'reviewer_name', 'decision',
        ]);
        if ($review['schema_version'] !== 1
            || $review['source_sha256'] !== $sourceSha256
            || $review['acceptance_protocol_schema_version'] !== $protocol['schema_version']
            || $review['acceptance_protocol_sha256'] !== $protocol['sha256']
            || $review['decision'] !== 'accepted'
            || ! is_string($review['reviewer_reference']) || trim($review['reviewer_reference']) === ''
            || ! is_string($review['reviewer_name']) || trim($review['reviewer_name']) === ''
            || $review['reviewer_reference'] !== ($entry['reviewer_reference'] ?? null)) {
            throw new RuntimeException('The review artifact does not approve these exact plan bytes under the current acceptance protocol.');
        }
        $this->exactKeys($entry, [
            'provenance_kind', 'source_sha256', 'review_evidence_sha256', 'reviewer_evidence_sha256',
            'author_reference', 'truth_reference', 'truth_sha256', 'truth_attributed_to',
            'review_evidence_reference', 'reviewer_reference',
        ]);
        foreach (['author_reference', 'truth_reference', 'truth_attributed_to', 'review_evidence_reference', 'reviewer_reference'] as $field) {
            if (! is_string($entry[$field]) || trim($entry[$field]) === '') {
                throw new RuntimeException('Configured acceptance evidence references must be attributable.');
            }
        }
        $truthSha256 = $this->sha256($entry['truth_sha256'], 'Independent truth hash');
        $sourceReference = 'tests/Fixtures/TestKit/'.$sourceKey.'.json';
        $reviewReference = 'tests/Fixtures/TestKit/'.$sourceKey.'.review.json';
        $reviewerReference = 'tests/Fixtures/TestKit/'.$sourceKey.'.review.md';
        if ($entry['truth_reference'] !== $sourceReference
            || ! hash_equals($sourceSha256, $truthSha256)
            || $entry['review_evidence_reference'] !== $reviewReference
            || $entry['reviewer_reference'] !== $reviewerReference) {
            throw new RuntimeException('Truth and review references must identify these exact checked-in source and review bytes.');
        }

        return [
            'authored_plan' => $authoredPlan,
            'truth_source' => [
                'reference' => $entry['truth_reference'],
                'sha256' => $truthSha256,
                'attributed_to' => $entry['truth_attributed_to'],
            ],
            'source_key' => $sourceKey,
            'source_sha256' => $sourceSha256,
            'author_reference' => $entry['author_reference'],
            'review_evidence_reference' => $entry['review_evidence_reference'],
            'review_evidence_sha256' => $reviewSha256,
            'reviewer_reference' => $reviewerReference,
            'reviewer_evidence_sha256' => $reviewerEvidenceSha256,
            'provenance_kind' => $entry['provenance_kind'],
            'source_evidence' => [
                'source_key' => $sourceKey,
                'registry_sha256' => $registrySha256,
                'source_sha256' => $sourceSha256,
                'author_reference' => $entry['author_reference'],
                'review_evidence_reference' => $reviewReference,
                'review_evidence_sha256' => $reviewSha256,
                'reviewer_reference' => $reviewerReference,
                'reviewer_evidence_sha256' => $reviewerEvidenceSha256,
                'provenance_kind' => $entry['provenance_kind'],
            ],
            'truth_source_references' => [[
                'reference' => $entry['truth_reference'],
                'sha256' => $truthSha256,
            ]],
        ];
    }

    private function readCheckedInArtifact(string $root, string $path): string
    {
        if (is_link($path) || ! is_file($path) || ! is_dir($root) || is_link($root)) {
            throw new RuntimeException('Acceptance source or review evidence is unavailable as a regular checked-in file.');
        }
        $realRoot = realpath($root);
        $realPath = realpath($path);
        $size = filesize($path);
        if ($realRoot === false || $realPath === false || $size === false || $size > self::MAX_ARTIFACT_BYTES
            || ! str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)
            || $realPath !== $path) {
            throw new RuntimeException('Acceptance source or review evidence is oversized or escapes its checked-in fixture directory.');
        }

        return file_get_contents($path) ?: '';
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     */
    private function assertSupportedMetrics(array $metrics): void
    {
        foreach ($metrics as $metric) {
            if (! in_array($metric['rule_kind'] ?? null, self::SUPPORTED_RULES, true)) {
                throw new RuntimeException('The Test Kit acceptance plan includes an unsupported scoring rule.');
            }
        }
    }

    /** @param array<string, mixed> $plan */
    private function assertPlanShape(array $plan): void
    {
        if (array_diff(array_keys($plan), ['schema_version', 'truth_source', 'cases', 'source_evidence']) !== []
            || array_diff(['schema_version', 'truth_source', 'cases'], array_keys($plan)) !== []
            || $plan['schema_version'] !== self::PLAN_SCHEMA_VERSION
            || ! is_array($plan['truth_source'])
            || ! is_array($plan['cases']) || ! array_is_list($plan['cases'])
            || count($plan['cases']) < 3 || count($plan['cases']) > self::MAX_CASES
            || (array_key_exists('source_evidence', $plan) && ! is_array($plan['source_evidence']))) {
            throw new RuntimeException('The independently authored Test Kit acceptance plan has an invalid schema or case count.');
        }
    }

    /** @param array<string, mixed> $source */
    private function assertAttribution(array $source): void
    {
        $expected = ['reference', 'sha256', 'attributed_to'];
        if (count($source) !== count($expected) || array_diff($expected, array_keys($source)) !== []
            || ! is_string($source['reference']) || trim($source['reference']) === ''
            || strlen($source['reference']) > self::MAX_TRUTH_REFERENCE_LENGTH
            || ! is_string($source['attributed_to']) || trim($source['attributed_to']) === ''
            || strlen($source['attributed_to']) > 200) {
            throw new RuntimeException('Independent response truth must have attributable reference metadata.');
        }
        $this->sha256($source['sha256'], 'Independent truth hash');
    }

    /** @param array<string, mixed> $source */
    private function canonicalSourceEvidence(array $source): array
    {
        $this->exactKeys($source, [
            'source_key', 'registry_sha256', 'source_sha256', 'author_reference',
            'review_evidence_reference', 'review_evidence_sha256', 'reviewer_reference',
            'reviewer_evidence_sha256', 'provenance_kind',
        ]);
        if (! is_string($source['source_key'])
            || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $source['source_key'])
            || ! is_string($source['author_reference']) || trim($source['author_reference']) === ''
            || strlen($source['author_reference']) > self::MAX_TRUTH_REFERENCE_LENGTH
            || ! is_string($source['review_evidence_reference']) || trim($source['review_evidence_reference']) === ''
            || strlen($source['review_evidence_reference']) > self::MAX_TRUTH_REFERENCE_LENGTH
            || ! is_string($source['reviewer_reference']) || trim($source['reviewer_reference']) === ''
            || strlen($source['reviewer_reference']) > self::MAX_TRUTH_REFERENCE_LENGTH
            || ! in_array($source['provenance_kind'], ['synthetic_software_fixture', 'reviewed_software_plan'], true)) {
            throw new RuntimeException('Authored plan source and software-review identities must be bounded and attributable.');
        }
        $this->sha256($source['registry_sha256'], 'Acceptance-source registry hash');
        $this->sha256($source['source_sha256'], 'Authored source hash');
        $this->sha256($source['review_evidence_sha256'], 'Software review evidence hash');
        $this->sha256($source['reviewer_evidence_sha256'], 'Reviewer evidence hash');

        return $source;
    }

    /**
     * @param  list<array<string,mixed>>  $authoredCases
     * @param  array<string,mixed>  $contract
     * @return list<array<string,mixed>>
     */
    private function canonicalCases(array $authoredCases, array $contract, int $pageCount): array
    {
        if ($pageCount < 1 || $pageCount > self::MAX_PAGES) {
            throw new RuntimeException('The acceptance plan exceeds the supported one-to-four-page inventory.');
        }
        $bindingsByPage = [];
        foreach ($contract['bindings'] as $binding) {
            $bindingsByPage[$binding['page_no']][$binding['native_label']] = $binding;
        }
        $metricsById = [];
        $metricsByCode = [];
        foreach ($contract['metrics'] as $metric) {
            $metricsById[(int) $metric['metric_id']] = $metric;
            $metricsByCode[(string) $metric['code']] = $metric;
        }
        $caseIds = [];
        $cases = [];
        foreach ($authoredCases as $authoredCase) {
            if (! is_array($authoredCase)) {
                throw new RuntimeException('Every authored test case must be an object.');
            }
            $this->exactKeys($authoredCase, ['case_id', 'coverage', 'pages', 'expected_metrics', 'recorded_measures']);
            $caseId = $authoredCase['case_id'];
            if (! is_string($caseId) || trim($caseId) === '' || strlen($caseId) > self::MAX_CASE_ID_LENGTH
                || isset($caseIds[$caseId])) {
                throw new RuntimeException('Test Kit case identifiers must be non-empty, bounded, and unique.');
            }
            $caseIds[$caseId] = true;
            $cases[] = $this->canonicalCase($authoredCase, $bindingsByPage, $metricsById, $metricsByCode, $pageCount);
        }
        usort($cases, static fn (array $left, array $right): int => strcmp($left['case_id'], $right['case_id']));

        return $cases;
    }

    /** @return array<string,mixed> */
    private function canonicalCase(array $case, array $bindingsByPage, array $metricsById, array $metricsByCode, int $pageCount): array
    {
        $coverage = $case['coverage'];
        if (! is_array($coverage) || ! array_is_list($coverage) || $coverage === []
            || array_diff($coverage, ['correct', 'incorrect', 'blank']) !== []
            || count(array_unique($coverage)) !== count($coverage)) {
            throw new RuntimeException('Each case must declare unique correct, incorrect, or blank coverage labels.');
        }

        if (! is_array($case['pages']) || ! array_is_list($case['pages']) || count($case['pages']) !== $pageCount) {
            throw new RuntimeException('Every test case must declare every physical page exactly once.');
        }
        $pagesByNo = [];
        foreach ($case['pages'] as $page) {
            if (! is_array($page)) {
                throw new RuntimeException('Each test case page must be an object.');
            }
            $this->exactKeys($page, ['page_no', 'responses']);
            $pageNo = $page['page_no'];
            if (! is_int($pageNo) || $pageNo < 1 || $pageNo > $pageCount || isset($pagesByNo[$pageNo])
                || ! is_array($page['responses']) || ! array_is_list($page['responses'])) {
                throw new RuntimeException('Each test case must declare unique valid page numbers and response lists.');
            }
            $expectedBindings = $bindingsByPage[$pageNo] ?? [];
            $responsesByLabel = [];
            foreach ($page['responses'] as $response) {
                if (! is_array($response)) {
                    throw new RuntimeException('Each native response must be an object.');
                }
                $this->exactKeys($response, ['native_label', 'token']);
                $label = $response['native_label'];
                if (! is_string($label) || ! isset($expectedBindings[$label]) || array_key_exists($label, $responsesByLabel)) {
                    throw new RuntimeException('A response label is unknown or duplicated for its physical page.');
                }
                $token = $response['token'];
                if ($token !== null && (! is_string($token) || ! in_array($token, $expectedBindings[$label]['option_tokens'], true))) {
                    throw new RuntimeException('A response token must exactly match its native field tokens or be confirmed blank (null).');
                }
                $responsesByLabel[$label] = ['native_label' => $label, 'token' => $token];
            }
            if (count($responsesByLabel) !== count($expectedBindings)) {
                throw new RuntimeException('Each case must declare every mapped native label, including explicit null for a confirmed blank.');
            }
            $ordered = [];
            foreach ($expectedBindings as $label => $binding) {
                $ordered[] = $responsesByLabel[$label];
            }
            $pagesByNo[$pageNo] = ['page_no' => $pageNo, 'responses' => $ordered];
        }
        if (count($pagesByNo) !== $pageCount) {
            throw new RuntimeException('A test case omitted a sheet page.');
        }
        ksort($pagesByNo);

        $responses = [];
        foreach ($pagesByNo as $page) {
            foreach ($page['responses'] as $response) {
                $responses[$page['page_no']][$response['native_label']] = $response['token'];
            }
        }
        $actualCoverage = [];
        foreach ($bindingsByPage as $pageNo => $bindings) {
            foreach ($bindings as $label => $binding) {
                if ($binding['correct_token'] === null) {
                    continue;
                }
                $token = $responses[$pageNo][$label];
                $actualCoverage[$token === null ? 'blank' : ($token === $binding['correct_token'] ? 'correct' : 'incorrect')] = true;
            }
        }
        if (array_diff(array_keys($actualCoverage), $coverage) !== [] || array_diff($coverage, array_keys($actualCoverage)) !== []) {
            throw new RuntimeException('Declared case coverage does not describe its independently authored response vector.');
        }

        $expectedMetrics = $this->canonicalExpectedMetrics($case['expected_metrics'], $metricsById, $metricsByCode);
        $recordedMeasures = $this->canonicalRecordedMeasures($case['recorded_measures'], $metricsById, $metricsByCode);

        return [
            'case_id' => $case['case_id'],
            'coverage' => array_values(array_intersect(['correct', 'incorrect', 'blank'], $coverage)),
            'pages' => array_values($pagesByNo),
            'expected_metrics' => $expectedMetrics,
            'recorded_measures' => $recordedMeasures,
        ];
    }

    /** @return list<array{metric_id:int,value:string}> */
    private function canonicalExpectedMetrics(mixed $rows, array $metricsById, array $metricsByCode): array
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('Every test case must provide independently worked metric outputs.');
        }
        $byId = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Every expected metric must be an object.');
            }
            $this->exactKeys($row, ['metric_code', 'value']);
            $code = $row['metric_code'];
            if (! is_string($code) || ! isset($metricsByCode[$code]) || ! is_string($row['value'])) {
                throw new RuntimeException('Expected metrics must reference known metric codes and decimal outputs.');
            }
            $id = (int) $metricsByCode[$code]['metric_id'];
            if (isset($byId[$id])) {
                throw new RuntimeException('Expected metric codes must be unique within each case.');
            }
            $metric = $metricsById[$id];
            $value = $this->decimal($row['value'], (int) $metric['precision_scale']);
            if ($this->compareDecimal($value, $metric['min_raw_value']) < 0 || $this->compareDecimal($value, $metric['max_raw_value']) > 0) {
                throw new RuntimeException('An independently authored expected metric is outside its declared range.');
            }
            $byId[$id] = ['metric_id' => $id, 'metric_code' => $code, 'value' => $value];
        }
        foreach ($metricsById as $id => $metric) {
            if ($metric['is_required'] && ! isset($byId[$id])) {
                throw new RuntimeException('A required metric output is missing from a test case.');
            }
        }
        ksort($byId);

        return array_values($byId);
    }

    private function canonicalRecordedMeasures(mixed $rows, array $metricsById, array $metricsByCode): array
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('Recorded measures must be an authored list.');
        }
        $byId = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Each recorded measure must be an object.');
            }
            $this->exactKeys($row, ['metric_code', 'value', 'procedure_reference']);
            $code = $row['metric_code'];
            if (! is_string($code) || ! isset($metricsByCode[$code]) || ! is_string($row['procedure_reference']) || ! is_string($row['value'])) {
                throw new RuntimeException('Recorded measures must identify a known metric and its approved procedure.');
            }
            $id = (int) $metricsByCode[$code]['metric_id'];
            if (isset($byId[$id]) || $metricsById[$id]['rule_kind'] !== 'recorded_measure'
                || $row['procedure_reference'] !== $metricsById[$id]['procedure_reference']) {
                throw new RuntimeException('A recorded measure must identify a supported metric and its exact approved procedure.');
            }
            $value = $this->decimal($row['value'], (int) $metricsById[$id]['precision_scale']);
            if ($this->compareDecimal($value, $metricsById[$id]['min_raw_value']) < 0
                || $this->compareDecimal($value, $metricsById[$id]['max_raw_value']) > 0) {
                throw new RuntimeException('A recorded measure is outside its approved metric range.');
            }
            $byId[$id] = ['metric_id' => $id, 'metric_code' => $code, 'value' => $value, 'procedure_reference' => $row['procedure_reference']];
        }
        foreach ($metricsById as $id => $metric) {
            if ($metric['rule_kind'] === 'recorded_measure' && $metric['is_required'] && ! isset($byId[$id])) {
                throw new RuntimeException('A required recorded measure and procedure identity are missing.');
            }
        }
        ksort($byId);

        return array_values($byId);
    }

    /** @param list<array<string,mixed>> $cases @param array<string,mixed> $contract */
    private function assertCoverage(array $cases, array $contract): void
    {
        $seenCoverage = [];
        $seenTokens = [];
        $rulesCovered = [];
        foreach ($contract['metrics'] as $metric) {
            $rulesCovered[$metric['rule_kind']] = false;
        }
        foreach ($cases as $case) {
            foreach ($case['coverage'] as $coverage) {
                $seenCoverage[$coverage] = true;
            }
            foreach ($case['expected_metrics'] as $output) {
                $metric = $this->metric($contract['metrics'], $output['metric_id']);
                $rulesCovered[$metric['rule_kind']] = true;
            }
            foreach ($case['pages'] as $page) {
                foreach ($page['responses'] as $response) {
                    $binding = $this->binding($contract['bindings'], $page['page_no'], $response['native_label']);
                    if ($response['token'] !== null) {
                        $seenTokens[$page['page_no']][$response['native_label']][$response['token']] = true;
                    }
                    if ($binding['correct_token'] !== null && $response['token'] === null) {
                        $seenCoverage['blank'] = true;
                    } elseif ($binding['correct_token'] !== null && $response['token'] === $binding['correct_token']) {
                        $seenCoverage['correct'] = true;
                    } elseif ($binding['correct_token'] !== null) {
                        $seenCoverage['incorrect'] = true;
                    }
                }
            }
        }
        foreach (['correct', 'incorrect', 'blank'] as $required) {
            if (! isset($seenCoverage[$required])) {
                throw new RuntimeException('Acceptance requires deliberate correct, incorrect, and blank cases.');
            }
        }
        foreach ($contract['bindings'] as $binding) {
            foreach ($binding['option_tokens'] as $token) {
                if (! isset($seenTokens[$binding['page_no']][$binding['native_label']][$token])) {
                    throw new RuntimeException('Acceptance vectors must exercise every exact native option token.');
                }
            }
        }
        foreach ($rulesCovered as $covered) {
            if (! $covered) {
                throw new RuntimeException('Acceptance vectors must include expected outputs for every applicable scoring rule.');
            }
        }
    }

    /** @param list<array<string,mixed>> $metrics */
    private function metric(array $metrics, int $id): array
    {
        foreach ($metrics as $metric) {
            if ((int) $metric['metric_id'] === $id) {
                return $metric;
            }
        }

        throw new RuntimeException('Expected output references an unknown metric.');
    }

    /** @param list<array<string,mixed>> $bindings */
    private function binding(array $bindings, int $pageNo, string $label): array
    {
        foreach ($bindings as $binding) {
            if ((int) $binding['page_no'] === $pageNo && $binding['native_label'] === $label) {
                return $binding;
            }
        }

        throw new RuntimeException('The authored response does not match the exact Test Kit item map.');
    }

    /** @param array<string,mixed> $row @param list<string> $keys */
    private function exactKeys(array $row, array $keys): void
    {
        if (count($row) !== count($keys) || array_diff(array_keys($row), $keys) !== [] || array_diff($keys, array_keys($row)) !== []) {
            throw new RuntimeException('The acceptance plan contains unsupported or missing fields.');
        }
    }

    private function decimal(string $value, int $scale): string
    {
        if (! preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/D', $value)) {
            throw new RuntimeException('Expected raw outputs must be finite decimal strings.');
        }
        $fraction = str_contains($value, '.') ? strlen(substr(strrchr($value, '.'), 1)) : 0;
        if ($fraction > $scale) {
            throw new RuntimeException('Expected raw output exceeds the metric precision scale.');
        }
        if ($value === '-0' || preg_match('/^-0(?:\.0+)?$/D', $value)) {
            return $scale === 0 ? '0' : '0.'.str_repeat('0', $scale);
        }
        if ($scale === 0) {
            return $value;
        }
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($decimal, $scale, '0');
    }

    private function compareDecimal(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo(BigDecimal::of($right));
    }

    /** @param array<mixed> $value */
    private function canonicalize(array $value): array
    {
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = array_is_list($child) ? array_map(fn (mixed $row): mixed => is_array($row) ? $this->canonicalize($row) : $row, $child) : $this->canonicalize($child);
            }
        }
        unset($child);
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sha256(mixed $value, string $field): string
    {
        if (! is_string($value) || ! preg_match('/^[a-f0-9]{64}$/D', $value)) {
            throw new RuntimeException($field.' must be a lowercase SHA-256 digest.');
        }

        return $value;
    }

    /** @return array<string, string> */
    public function expectedWorkerIdentity(): array
    {
        $identity = [
            'contract_version' => (string) config('omr.omrchecker.contract_version', ''),
            'engine_name' => (string) config('omr.omrchecker.engine_name', ''),
            'engine_commit' => (string) config('omr.omrchecker.engine_commit', ''),
            'patch_identity' => (string) config('omr.omrchecker.patch_identity', ''),
        ];
        foreach ($identity as $value) {
            if (trim($value) === '') {
                throw new RuntimeException('The configured Test Kit worker identity is incomplete.');
            }
        }

        return $identity;
    }

    private function protocolRules(): array
    {
        return [
            'schema_version' => self::PROTOCOL_RULES_SCHEMA_VERSION,
            'case_identity' => 'case_id',
            'page_identity' => 'page_no',
            'response_identity' => ['page_no', 'native_label'],
            'blank_representation' => null,
            'coverage_required' => ['correct', 'incorrect', 'blank'],
            'require_every_option_token' => true,
            'require_all_supported_rules' => self::SUPPORTED_RULES,
            'required_metric_outputs' => 'independently_authored_decimal_by_metric_code',
            'recorded_measure_procedure_match' => 'exact_metric_procedure_reference',
            'case_limit' => self::MAX_CASES,
        ];
    }
}
