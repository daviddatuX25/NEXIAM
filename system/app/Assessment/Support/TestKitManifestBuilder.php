<?php

namespace App\Assessment\Support;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSection;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use BackedEnum;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Creates and verifies the immutable, key-safe material identity for a Test Kit.
 * Acceptance protocol, plan, specimens, receipts, and proof are deliberately
 * outside this builder and cannot contribute to the material hash.
 */
final class TestKitManifestBuilder
{
    public const MANIFEST_SCHEMA_VERSION = 3;

    public const CANONICALIZER_VERSION = 'nexiam.test-kit.material-canonicalizer.v3.1';

    public const LEGACY_V3_CANONICALIZER_VERSION = 'nexiam.test-kit.material-canonicalizer.v3';

    private const HISTORICAL_V2_MANIFEST_SCHEMA_VERSION = 2;

    private const HISTORICAL_V2_CANONICALIZER_VERSION = 'nexiam.test-kit.material-canonicalizer.v2';

    private const HISTORICAL_V1_MANIFEST_SCHEMA_VERSION = 1;

    private const HISTORICAL_V1_CANONICALIZER_VERSION = 'nexiam.test-kit.material-canonicalizer.v1';

    private const MATERIAL_ASSET_KINDS = [
        'source',
        'intermediate_page',
        'raster_page',
        'print_pdf',
        'marker_profile',
        'native_template',
        'native_config',
        'native_manifest',
        'reference_page',
    ];

    public function __construct(
        private readonly ExactTestKitItemMap $itemMap = new ExactTestKitItemMap,
        private readonly ?KitCompositionContext $compositionContext = null,
    ) {}

    /**
     * Build the default schema-3 conditional manifest from the exact approved
     * profile declarations and the explicitly selected applicable materials.
     *
     * @return array{manifest:array<string, mixed>,sha256:string,composition_sha256:?string}
     */
    public function build(
        ?OmrAnswerSheetTemplateVersion $template,
        ?OmrQuestionBookletVersion $booklet,
        ?OmrBookletSet $set,
        TestInstrumentVersion $profile,
        int $institutionId,
        array $kitIdentity,
        ?array $compositionRows,
        array $componentVector,
        int $compositionRevision,
        bool $legacyV3 = false,
    ): array {
        $identity = $this->kitIdentity($kitIdentity);
        $institutionId = $this->positiveId($institutionId, 'Institution');
        $profile->loadMissing(['instrument', 'subtests.items', 'metrics.inputs']);
        foreach ([$template, $booklet, $profile] as $component) {
            if ($component !== null) {
                $component->loadMissing(match (true) {
                    $component instanceof OmrAnswerSheetTemplateVersion => ['template', 'pages', 'canonicalAssets'],
                    $component instanceof OmrQuestionBookletVersion => ['booklet', 'items.section'],
                    default => ['instrument'],
                });
            }
        }
        if ((int) ($componentVector['test_kit_id'] ?? 0) !== $identity['test_kit_id']
            || (int) ($componentVector['institution_id'] ?? 0) !== $institutionId
            || ! is_bool($componentVector['is_synthetic'] ?? null)
            || $componentVector['is_synthetic'] !== ($identity['provenance_class'] === 'synthetic')
            || (int) ($componentVector['test_instrument_version_id'] ?? 0) !== (int) $profile->getKey()
            || (int) ($componentVector['profile']['instrument_id'] ?? 0) !== (int) $profile->test_instrument_id
            || (int) $profile->test_instrument_id < 1) {
            throw new RuntimeException('The schema-3 component vector does not match the exact Kit, profile, institution, and provenance.');
        }

        $profileContract = $this->itemMap->buildProfileContract($profile, $componentVector);
        $requirements = $this->requirements($profile, $profileContract['normalized_metrics']);
        $omrApplicable = $requirements['omr_applicable'];
        $requiredResources = $requirements['required_resources'];
        $requiresSheet = in_array('answer_sheet', $requiredResources, true);
        $requiresBooklet = in_array('booklet_form', $requiredResources, true);
        $requiresKey = in_array('key', $requiredResources, true);
        $selectedResourcesMatch = $legacyV3
            ? ($requiresSheet === ($template !== null)
                && $requiresBooklet === ($booklet !== null && $set !== null)
                && ($requiresBooklet || ($booklet === null && $set === null)))
            : ((! $requiresSheet || $template !== null)
                && (! $requiresBooklet || ($booklet !== null && $set !== null))
                && (($booklet !== null) === ($set !== null)));
        $hasOptionalAttachment = ! $legacyV3
            && $template !== null && $booklet !== null && $set !== null
            && (! $requiresSheet || ! $requiresBooklet);
        $mapApplicable = $omrApplicable || $hasOptionalAttachment;
        if (! $selectedResourcesMatch
            || ($requiresKey && ! $requiresBooklet)
            || (int) ($componentVector['template_version_id'] ?? 0) !== (int) ($template?->getKey() ?? 0)
            || (int) ($componentVector['booklet_version_id'] ?? 0) !== (int) ($booklet?->getKey() ?? 0)
            || (int) ($componentVector['booklet_set_id'] ?? 0) !== (int) ($set?->getKey() ?? 0)
            || ($mapApplicable && $compositionRevision < 1)) {
            throw new RuntimeException('Schema-3 exact components must match the approved requirements and any complete optional attachment.');
        }
        if ($mapApplicable !== ($compositionRows !== null)
            || ($mapApplicable && ($template === null || $booklet === null || $set === null))) {
            throw new RuntimeException('An exact saved item map is required for declared OMR or an optional paper attachment.');
        }
        if ($template !== null && ! in_array($template->status, [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE], true)) {
            throw new RuntimeException('A Test Kit requires a compiled Answer Sheet version.');
        }
        if ($booklet !== null && ($booklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED || ! $booklet->isFinalized())) {
            throw new RuntimeException('A Test Kit requires a finalized exact Booklet version.');
        }
        $this->assertConditionalOwnership($template, $booklet, $profile, $institutionId, $identity['provenance_class']);

        $composition = null;
        $map = null;
        $compositionSha256 = null;
        if ($mapApplicable) {
            $contract = $this->itemMap->build($template, $booklet, $set, $profile, $compositionRows, $componentVector);
            $compositionSha256 = (string) $contract['composition_sha256'];
            $manifestRows = array_map(static function (array $row): array {
                unset($row['template_version_id'], $row['booklet_version_id'], $row['booklet_set_id'], $row['test_instrument_version_id']);

                return $row;
            }, $contract['composition_rows']);
            $composition = [
                'revision' => $compositionRevision,
                'component_vector_sha256' => $this->hashArray($componentVector),
                'component_vector' => $this->canonicalize($componentVector),
                'sha256' => $compositionSha256,
                'rows' => $manifestRows,
            ];
            $map = [
                'items' => $this->manifestItems($profile, $contract),
                'bindings' => $this->manifestBindings($contract['bindings']),
            ];
        }

        $sheet = $template === null ? null : $this->schemaThreeSheet($template, $componentVector, $mapApplicable, $contract ?? []);
        $bookletMaterial = $booklet === null ? null : $this->schemaThreeBooklet($booklet, $set, $requiresKey || $hasOptionalAttachment);
        $optionalAttachment = $hasOptionalAttachment ? [
            'version' => 1,
            'answer_sheet_version_id' => (int) $template->id,
            'booklet_version_id' => (int) $booklet->id,
            'booklet_set_id' => (int) $set->id,
            'component_vector_sha256' => $this->hashArray($componentVector),
            'composition_sha256' => $compositionSha256,
            'set_order_sha256' => $this->sha256($set->order_hash, 'Set A order hash'),
            'set_key_sha256' => $this->sha256($set->answer_key_hash, 'Set A key hash'),
        ] : null;
        $manifest = [
            'manifest_schema_version' => self::MANIFEST_SCHEMA_VERSION,
            'canonicalizer_version' => $legacyV3
                ? self::LEGACY_V3_CANONICALIZER_VERSION
                : self::CANONICALIZER_VERSION,
            'institution_id' => $institutionId,
            'kit' => $identity,
            'requirements' => $requirements,
            'sheet' => $sheet,
            'booklet' => $bookletMaterial,
            ...($legacyV3 ? [] : ['optional_attachment' => $optionalAttachment]),
            'composition' => $composition,
            'profile' => $this->profileMaterial($profile, [
                'content_sha256' => $profileContract['content_sha256'],
            ]),
            'item_map' => $map,
            'scoring' => [
                'metrics' => $profileContract['metrics'],
                'profile_items' => $profileContract['profile_items'],
                'raw_evaluator_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
            ],
        ];
        $manifest = $this->canonicalize($manifest);

        return [
            'manifest' => $manifest,
            'sha256' => $this->hashManifest($manifest),
            'composition_sha256' => $compositionSha256,
        ];
    }

    /**
     * Rebuild only retained schema-2 paper manifests. Never use this for new work.
     *
     * @return array{manifest:array<string, mixed>,sha256:string,composition_sha256:string}
     */
    public function buildHistoricalV2(
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        OmrBookletSet $set,
        TestInstrumentVersion $profile,
        int $institutionId,
        array $kitIdentity,
        array $compositionRows,
        array $componentVector,
        int $compositionRevision = 1,
    ): array {
        $identity = $this->kitIdentity($kitIdentity);
        $institutionId = $this->positiveId($institutionId, 'Institution');
        if ($institutionId < 1) {
            throw new RuntimeException('A Test Kit manifest requires an institution identity.');
        }

        $template->loadMissing(['template', 'pages', 'canonicalAssets']);
        $booklet->loadMissing(['booklet', 'items.section']);
        $profile->loadMissing('instrument');

        $this->assertComponentOwnership($template, $booklet, $profile, $institutionId, $identity['provenance_class']);
        if (! in_array($template->status, [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE], true)) {
            throw new RuntimeException('A Test Kit requires a compiled Answer Sheet version.');
        }
        $sourceKind = $this->sourceKind($template);
        $normalizedProfile = (new TestInstrumentProfileValidator)->normalizeVersion($profile);
        $profileDigest = (new TestInstrumentProfileValidator)->digest($normalizedProfile);
        if (! hash_equals($profileDigest, $this->sha256($profile->content_sha256, 'Approved profile content hash'))) {
            throw new RuntimeException('The approved profile content hash does not match its exact profile and scoring rules.');
        }
        foreach ($compositionRows as &$row) {
            if (! array_key_exists('template_version_id', $row)) {
                $row = [
                    'template_version_id' => $componentVector['template_version_id'] ?? null,
                    'booklet_version_id' => $componentVector['booklet_version_id'] ?? null,
                    'booklet_set_id' => $componentVector['booklet_set_id'] ?? null,
                    'test_instrument_version_id' => $componentVector['test_instrument_version_id'] ?? null,
                    ...$row,
                ];
            }
        }
        unset($row);
        $contract = $this->itemMap->build($template, $booklet, $set, $profile, $compositionRows, $componentVector);
        $componentVector = $contract['component_vector'] ?? $componentVector;
        $templateId = $this->positiveId($template->getKey(), 'Answer Sheet version');
        $bookletId = $this->positiveId($booklet->getKey(), 'Booklet version');
        $setId = $this->positiveId($set->getKey(), 'Booklet Set');
        $profileId = $this->positiveId($profile->getKey(), 'Test Instrument profile');
        if ((int) ($componentVector['test_kit_id'] ?? 0) !== $identity['test_kit_id']
            || (int) ($componentVector['institution_id'] ?? 0) !== $institutionId
            || ! is_bool($componentVector['is_synthetic'] ?? null)
            || $componentVector['is_synthetic'] !== ($identity['provenance_class'] === 'synthetic')) {
            throw new RuntimeException('The component vector does not belong to this Test Kit owner and provenance.');
        }
        $componentVectorSha256 = hash('sha256', json_encode(
            $this->canonicalize($componentVector),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
        if ($compositionRevision < 1 || preg_match('/^[a-f0-9]{64}$/D', $componentVectorSha256) !== 1) {
            throw new RuntimeException('The Test Kit composition revision or component vector is invalid.');
        }
        $sheetHashes = $this->sheetHashes($template);
        $assets = $this->assets($template, $templateId);
        $sheetHashes['physical_inventory_sha256'] = $this->sha256(
            $componentVector['sheet']['physical_inventory_sha256'] ?? null,
            'Physical Sheet inventory hash',
        );
        $pages = $this->pages($template, $assets);
        $bookletSet = [
            'booklet_set_id' => $setId,
            'set_code' => (string) $set->set_code,
            'order_sha256' => $this->sha256($set->order_hash, 'Set A order hash'),
            'key_sha256' => $this->sha256($set->answer_key_hash, 'Set A key hash'),
        ];

        $manifestCompositionRows = array_map(static function (array $row): array {
            unset(
                $row['template_version_id'],
                $row['booklet_version_id'],
                $row['booklet_set_id'],
                $row['test_instrument_version_id'],
            );

            return $row;
        }, $contract['composition_rows']);

        $manifest = [
            'manifest_schema_version' => self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION,
            'canonicalizer_version' => self::HISTORICAL_V2_CANONICALIZER_VERSION,
            'institution_id' => $institutionId,
            'kit' => $identity,
            'sheet' => [
                'template_version_id' => $templateId,
                'template_id' => $this->positiveId($template->template_id, 'Answer Sheet template'),
                'version_number' => $this->positiveId($template->version, 'Answer Sheet version number'),
                'source_kind' => $sourceKind,
                'registration_mode' => $this->enumValue($template->registration_mode, 'Answer Sheet registration mode'),
                'qr_mode' => $this->enumValue($template->qr_mode, 'Answer Sheet QR mode'),
                'page_count' => $this->positiveId($template->page_count, 'Answer Sheet page count'),
                'identity_regions' => $this->canonicalize($this->requiredArray($template->identity_regions_json, 'Answer Sheet identity regions')),
                'print_overlay' => $this->canonicalize($template->print_overlay_json),
                'hashes' => $sheetHashes,
                'pages' => $pages,
                'live_geometry' => $contract['geometry'],
                'assets' => $assets,
                'software' => [
                    'engine_name' => $this->nullableString($template->engine_name),
                    'engine_commit' => $this->nullableString($template->engine_commit),
                    'patch_identity' => $this->nullableString($template->patch_identity),
                    'native_bundle_version' => $this->nullableString($template->native_bundle_version),
                    'layout_solver_version' => $this->nullableString($template->layout_solver_version),
                ],
            ],
            'booklet' => [
                'booklet_version_id' => $bookletId,
                'booklet_id' => $this->positiveId($booklet->booklet_id, 'Booklet'),
                'version_number' => $this->positiveId($booklet->version, 'Booklet version number'),
                'authoring_mode' => (string) $booklet->authoring_mode,
                'stored_question_content_sha256' => $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS
                    ? $this->sha256($booklet->content_hash, 'Stored Booklet question content hash')
                    : null,
                'intrinsic_items' => $this->intrinsicBookletItems($booklet),
                'set' => $bookletSet,
            ],
            'composition' => [
                'revision' => $compositionRevision,
                'component_vector_sha256' => $componentVectorSha256,
                'component_vector' => $this->canonicalize($componentVector),
                'sha256' => $contract['composition_sha256'],
                'rows' => $manifestCompositionRows,
            ],
            'profile' => $this->profileMaterial($profile, $contract),
            'item_map' => [
                'items' => $this->manifestItems($profile, $contract),
                'bindings' => $this->manifestBindings($contract['bindings']),
            ],
            'scoring' => [
                'metrics' => $contract['metrics'],
                'raw_evaluator_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
            ],
        ];

        $manifest = $this->canonicalize($manifest);

        return [
            'manifest' => $manifest,
            'sha256' => $this->hashManifest($manifest),
            'composition_sha256' => (string) $contract['composition_sha256'],
        ];
    }

    /**
     * Rebuilds the material contract from the immutable component pins and
     * compares both the canonical manifest bytes and their stored digest.
     * This does not evaluate acceptance, physical qualification, or source rights.
     */
    public function verify(TestKitVersion $version): void
    {
        $version = TestKitVersion::query()->findOrFail($version->getKey());
        $version->load([
            'testKit',
            'templateVersion.template',
            'templateVersion.pages',
            'templateVersion.canonicalAssets',
            'templateVersion.areas.questions.options',
            'bookletVersion.booklet',
            'bookletVersion.items.section',
            'bookletSet.items.bookletItem',
            'testInstrumentVersion.instrument',
            'testInstrumentVersion.subtests.items',
            'testInstrumentVersion.metrics.inputs',
            'compositionRows',
        ]);

        $stored = $version->getAttribute('manifest_json');
        $family = $version->testKit;
        $template = $version->templateVersion;
        $booklet = $version->bookletVersion;
        $set = $version->bookletSet;
        $profile = $version->testInstrumentVersion;
        if (! is_array($stored) || $family === null || $profile === null) {
            throw new RuntimeException('The Test Kit version is missing its immutable manifest or exact profile pin.');
        }
        $schemaVersion = (int) $version->manifest_schema_version;
        $canonicalizer = (string) $version->canonicalizer_version;
        $stored = $this->canonicalize($stored);
        $storedHash = $this->sha256($version->manifest_sha256, 'Stored Test Kit manifest hash');
        if (! hash_equals($storedHash, $this->hashManifest($stored))) {
            throw new RuntimeException('The stored Test Kit manifest hash does not match its canonical material.');
        }
        if ((int) $version->institution_id !== (int) $family->institution_id
            || $version->is_synthetic === null
            || (bool) $version->is_synthetic !== (bool) $family->is_synthetic) {
            throw new RuntimeException('The Test Kit version owner and provenance tuple does not match its family.');
        }

        if ($schemaVersion === self::HISTORICAL_V1_MANIFEST_SCHEMA_VERSION
            && $canonicalizer === self::HISTORICAL_V1_CANONICALIZER_VERSION
            && $version->composition_sha256 === null
            && $version->composition_rows_sealed_at === null) {
            if ($template === null || $booklet === null || $set === null) {
                throw new RuntimeException('The historical schema-1 Test Kit version is missing its original paper pins.');
            }
            $this->assertComponentOwnership(
                $template,
                $booklet,
                $profile,
                (int) $family->institution_id,
                (bool) $family->is_synthetic ? 'synthetic' : 'institutional',
            );

            return;
        }
        if (! in_array($schemaVersion, [
            self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION,
            self::MANIFEST_SCHEMA_VERSION,
        ], true)
            || $version->source_preparation_id === null
            || $version->source_preparation_revision === null
            || ($schemaVersion === self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION
                && ($version->composition_revision === null || $version->composition_rows_sealed_at === null))) {
            throw new RuntimeException('The sealed Test Kit manifest generation or source identity is incomplete.');
        }
        if (($schemaVersion === self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION
                && $canonicalizer !== self::HISTORICAL_V2_CANONICALIZER_VERSION)
            || ($schemaVersion === self::MANIFEST_SCHEMA_VERSION
                && ! in_array($canonicalizer, [
                    self::CANONICALIZER_VERSION,
                    self::LEGACY_V3_CANONICALIZER_VERSION,
                ], true))) {
            throw new RuntimeException('The sealed Test Kit manifest canonicalizer does not match its generation.');
        }

        $compositionContext = $this->compositionContext ?? app(KitCompositionContext::class);
        $resolved = $compositionContext->resolveVersion($version);
        $identity = [
            'test_kit_id' => $this->positiveId($version->test_kit_id, 'Test Kit family'),
            'public_id' => (string) $family->public_id,
            'version_number' => $this->positiveId($version->version_number, 'Test Kit version number'),
            'provenance_class' => (bool) $family->is_synthetic ? 'synthetic' : 'institutional',
        ];
        if ($schemaVersion === self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION) {
            if ($template === null || $booklet === null || $set === null) {
                throw new RuntimeException('The historical schema-2 Test Kit version is missing its original paper pins.');
            }
            $rebuilt = $this->buildHistoricalV2(
                $template,
                $booklet,
                $set,
                $profile,
                $this->positiveId($family->institution_id, 'Test Kit institution'),
                $identity,
                $resolved['rows'],
                $resolved['component_vector'],
                (int) $version->composition_revision,
            );
        } else {
            if (($resolved['requirements'] ?? null) === null || ! is_bool($resolved['omr_applicable'] ?? null)) {
                throw new RuntimeException('The schema-3 sealed profile has unresolved input applicability.');
            }
            $rebuilt = $this->build(
                $template,
                $booklet,
                $set,
                $profile,
                $this->positiveId($family->institution_id, 'Test Kit institution'),
                $identity,
                ($resolved['map_applicable'] ?? false) ? $resolved['rows'] : null,
                $resolved['component_vector'],
                (int) $version->composition_revision,
                $canonicalizer === self::LEGACY_V3_CANONICALIZER_VERSION,
            );
        }

        $sameComposition = $rebuilt['composition_sha256'] === null
            ? $version->composition_sha256 === null
            : is_string($version->composition_sha256)
                && hash_equals($rebuilt['composition_sha256'], $version->composition_sha256);
        $pinsMatch = (int) $version->test_instrument_version_id === (int) $profile->id
            && (int) $version->test_instrument_id === (int) $profile->test_instrument_id
            && (int) ($version->template_version_id ?? 0) === (int) ($template?->id ?? 0)
            && (int) ($version->booklet_version_id ?? 0) === (int) ($booklet?->id ?? 0)
            && (int) ($version->booklet_set_id ?? 0) === (int) ($set?->id ?? 0);
        if ($template !== null) {
            $pinsMatch = $pinsMatch
                && (int) $version->template_id === (int) $template->template_id;
        }
        if ($booklet !== null) {
            $pinsMatch = $pinsMatch
                && (int) $version->booklet_id === (int) $booklet->booklet_id;
        }
        if ($set !== null) {
            $keyPinMatches = $schemaVersion === self::HISTORICAL_V2_MANIFEST_SCHEMA_VERSION
                || in_array('key', $resolved['requirements']['required_resources'] ?? [], true)
                || (($stored['optional_attachment']['version'] ?? null) === 1)
                    ? hash_equals((string) $version->booklet_set_answer_key_hash, (string) $set->answer_key_hash)
                    : $version->booklet_set_answer_key_hash === null;
            $pinsMatch = $pinsMatch
                && (string) $version->booklet_set_code === (string) $set->set_code
                && hash_equals((string) $version->booklet_set_order_hash, (string) $set->order_hash)
                && $keyPinMatches;
        }
        if ($rebuilt['manifest'] !== $stored
            || ! hash_equals($rebuilt['sha256'], $storedHash)
            || ! $sameComposition
            || ! $pinsMatch) {
            throw new RuntimeException('The Test Kit manifest no longer matches its exact family, composition, and component pins.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $normalizedMetrics
     * @return array{
     *     schema_version:int,
     *     metrics:list<array{metric_id:int,code:string,input_contract_json:array<string,mixed>}>,
     *     required_resources:list<string>,
     *     omr_applicable:bool
     * }
     */
    private function requirements(TestInstrumentVersion $profile, array $normalizedMetrics): array
    {
        $declarationsByCode = [];
        foreach ($normalizedMetrics as $metric) {
            if (! is_array($metric) || ! is_string($metric['code'] ?? null)
                || ! is_array($metric['input_contract_json'] ?? null)) {
                throw new RuntimeException('Every schema-3 profile metric must have a declared canonical input contract.');
            }
            $declarationsByCode[$metric['code']] = $metric['input_contract_json'];
        }

        $metrics = [];
        $resources = [];
        $omrApplicable = false;
        foreach ($profile->metrics->sortBy([['code', 'asc'], ['id', 'asc']])->values() as $metric) {
            $metricId = $this->positiveId($metric->getKey(), 'Profile metric');
            $code = (string) $metric->code;
            $contract = $declarationsByCode[$code] ?? null;
            if ((int) $metric->test_instrument_version_id !== (int) $profile->getKey()
                || ! is_array($contract)
                || $metric->input_contract_json === null) {
                throw new RuntimeException('The exact approved profile metric is missing its immutable input contract.');
            }
            $normalized = (new TestInstrumentProfileValidator)->normalizeInputContract(
                $metric->input_contract_json,
                (string) ($metric->unit ?? ''),
                (string) $metric->rule_kind,
                "metrics.{$code}.input_contract_json",
            );
            if ($normalized !== $contract) {
                throw new RuntimeException('The profile input contract is not in its canonical approved form.');
            }
            $resources = [...$resources, ...$contract['required_resources']];
            $omrApplicable = $omrApplicable || in_array('omr', $contract['allowed_sources'], true);
            $metrics[] = [
                'metric_id' => $metricId,
                'code' => $code,
                'input_contract_json' => $contract,
            ];
        }
        usort($metrics, static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id']);
        $resources = array_values(array_unique($resources));
        sort($resources, SORT_STRING);

        return [
            'schema_version' => 1,
            'metrics' => $metrics,
            'required_resources' => $resources,
            'omr_applicable' => $omrApplicable,
        ];
    }

    private function assertConditionalOwnership(
        ?OmrAnswerSheetTemplateVersion $template,
        ?OmrQuestionBookletVersion $booklet,
        TestInstrumentVersion $profile,
        int $institutionId,
        string $provenanceClass,
    ): void {
        $expectedSynthetic = $provenanceClass === 'synthetic';
        $owners = [
            ['owner' => $profile->instrument, 'expected_id' => (int) $profile->test_instrument_id],
        ];
        if ($template !== null) {
            $owners[] = ['owner' => $template->template, 'expected_id' => (int) $template->template_id];
        }
        if ($booklet !== null) {
            $owners[] = ['owner' => $booklet->booklet, 'expected_id' => (int) $booklet->booklet_id];
        }
        foreach ($owners as $entry) {
            $owner = $entry['owner'];
            if ($owner === null
                || (int) $owner->getKey() !== $entry['expected_id']
                || $owner->getAttribute('institution_id') === null
                || $owner->getAttribute('is_synthetic') === null
                || (int) $owner->getAttribute('institution_id') !== $institutionId
                || (bool) $owner->getAttribute('is_synthetic') !== $expectedSynthetic) {
                throw new RuntimeException('Every selected material owner must match the Test Kit institution and provenance.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function schemaThreeSheet(
        OmrAnswerSheetTemplateVersion $template,
        array $componentVector,
        bool $omrApplicable,
        array $contract,
    ): array {
        $templateId = $this->positiveId($template->getKey(), 'Answer Sheet version');
        $sheetHashes = $this->sheetHashes($template);
        $sheetHashes['physical_inventory_sha256'] = $omrApplicable
            ? $this->sha256(
                $componentVector['sheet']['physical_inventory_sha256'] ?? null,
                'Physical Sheet inventory hash',
            )
            : null;
        $assets = $this->assets($template, $templateId);
        $sheet = [
            'template_version_id' => $templateId,
            'template_id' => $this->positiveId($template->template_id, 'Answer Sheet template'),
            'version_number' => $this->positiveId($template->version, 'Answer Sheet version number'),
            'source_kind' => $this->sourceKind($template),
            'registration_mode' => $this->enumValue($template->registration_mode, 'Answer Sheet registration mode'),
            'qr_mode' => $this->enumValue($template->qr_mode, 'Answer Sheet QR mode'),
            'page_count' => $this->positiveId($template->page_count, 'Answer Sheet page count'),
            'identity_regions' => $this->canonicalize($this->requiredArray($template->identity_regions_json, 'Answer Sheet identity regions')),
            'print_overlay' => $this->canonicalize($template->print_overlay_json),
            'hashes' => $sheetHashes,
            'pages' => $this->pages($template, $assets),
            'live_geometry' => $omrApplicable ? $contract['geometry'] : null,
            'assets' => $assets,
            'software' => [
                'engine_name' => $this->nullableString($template->engine_name),
                'engine_commit' => $this->nullableString($template->engine_commit),
                'patch_identity' => $this->nullableString($template->patch_identity),
                'native_bundle_version' => $this->nullableString($template->native_bundle_version),
                'layout_solver_version' => $this->nullableString($template->layout_solver_version),
            ],
        ];

        return $sheet;
    }

    /** @return array<string, mixed> */
    private function schemaThreeBooklet(OmrQuestionBookletVersion $booklet, ?OmrBookletSet $set, bool $keyApplicable): array
    {
        $booklet->loadMissing(['booklet', 'items.section']);
        if ($set !== null) {
            $set->loadMissing('items.bookletItem');
        }
        $material = [
            'booklet_version_id' => $this->positiveId($booklet->getKey(), 'Booklet version'),
            'booklet_id' => $this->positiveId($booklet->booklet_id, 'Booklet'),
            'version_number' => $this->positiveId($booklet->version, 'Booklet version number'),
            'authoring_mode' => (string) $booklet->authoring_mode,
            'stored_question_content_sha256' => $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS
                ? $this->sha256($booklet->content_hash, 'Stored Booklet question content hash')
                : null,
            'intrinsic_items' => $this->intrinsicBookletItems($booklet, strict: true),
            'set' => null,
        ];
        if ($set !== null) {
            if ((int) $set->booklet_version_id !== (int) $booklet->getKey()
                || $set->set_code !== 'A'
                || $set->status !== OmrBookletSet::STATUS_FINALIZED
                || $set->finalized_at === null) {
                throw new RuntimeException('The exact finalized Booklet Set A form does not match its Booklet version.');
            }
            $material['set'] = [
                'booklet_set_id' => $this->positiveId($set->getKey(), 'Booklet Set'),
                'set_code' => (string) $set->set_code,
                'order_sha256' => $this->sha256($set->order_hash, 'Booklet Set order hash'),
                'key_sha256' => $keyApplicable
                    ? $this->sha256($set->answer_key_hash, 'Booklet Set key hash')
                    : null,
            ];
        }

        return $material;
    }

    private function hashArray(array $value): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array{test_kit_id:int,public_id:string,version_number:int,provenance_class:string}
     */
    private function kitIdentity(array $identity): array
    {
        $expectedKeys = ['test_kit_id', 'public_id', 'version_number', 'provenance_class'];
        $keys = array_keys($identity);
        sort($keys, SORT_STRING);
        sort($expectedKeys, SORT_STRING);
        if ($keys !== $expectedKeys) {
            throw new RuntimeException('Kit identity must contain only the exact family, public, version, and provenance identities.');
        }
        $publicId = $identity['public_id'] ?? null;
        $provenance = $identity['provenance_class'] ?? null;
        if (! is_string($publicId) || trim($publicId) === ''
            || ! is_string($provenance) || ! in_array($provenance, ['institutional', 'synthetic'], true)) {
            throw new RuntimeException('Kit identity contains an invalid public or provenance identity.');
        }

        return [
            'test_kit_id' => $this->positiveId($identity['test_kit_id'] ?? null, 'Test Kit family'),
            'public_id' => $publicId,
            'version_number' => $this->positiveId($identity['version_number'] ?? null, 'Test Kit version number'),
            'provenance_class' => $provenance,
        ];
    }

    private function assertComponentOwnership(
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        TestInstrumentVersion $profile,
        int $institutionId,
        string $provenanceClass,
    ): void {
        $sheetOwner = $template->template;
        $bookletOwner = $booklet->booklet;
        $profileOwner = $profile->instrument;
        $expectedSynthetic = $provenanceClass === 'synthetic';
        foreach ([$sheetOwner, $bookletOwner, $profileOwner] as $owner) {
            if ($owner === null
                || $owner->getAttribute('institution_id') === null
                || $owner->getAttribute('is_synthetic') === null
                || (int) $owner->getAttribute('institution_id') !== $institutionId
                || (bool) $owner->getAttribute('is_synthetic') !== $expectedSynthetic) {
                throw new RuntimeException('Every material owner must match the Test Kit institution and provenance.');
            }
        }
        if ((int) $profileOwner->getKey() !== (int) $profile->test_instrument_id) {
            throw new RuntimeException('The approved profile version is not owned by its exact instrument.');
        }
    }

    /** @return array<string, string|null> */
    private function sheetHashes(OmrAnswerSheetTemplateVersion $template): array
    {
        $hashes = [
            'config_sha256' => $this->sha256($template->config_hash, 'Answer Sheet config hash'),
            'template_json_sha256' => $this->sha256($template->template_json_hash, 'Answer Sheet native template hash'),
            'native_bundle_sha256' => $this->sha256($template->native_bundle_hash, 'Answer Sheet native bundle hash'),
            'layout_blueprint_sha256' => $template->layout_blueprint_hash === null
                ? null
                : $this->sha256($template->layout_blueprint_hash, 'Answer Sheet layout blueprint hash'),
        ];

        return $hashes;
    }

    /**
     * @return list<array{id:int,kind:string,page_no:?int,sha256:string,mime:?string,bytes:?int}>
     */
    private function assets(OmrAnswerSheetTemplateVersion $template, int $templateId): array
    {
        $assets = [];
        foreach ($template->canonicalAssets as $asset) {
            $kind = (string) $asset->kind;
            if (in_array($kind, ['evidence', 'digital_proof'], true)) {
                continue;
            }
            if (! in_array($kind, self::MATERIAL_ASSET_KINDS, true)
                || (int) $asset->template_version_id !== $templateId) {
                throw new RuntimeException('The Answer Sheet contains an unsupported or cross-version material asset.');
            }
            $pageNo = $asset->page_no === null ? null : $this->positiveId($asset->page_no, 'Asset page number');
            $assets[] = [
                'id' => $this->positiveId($asset->getKey(), 'Answer Sheet asset'),
                'kind' => $kind,
                'page_no' => $pageNo,
                'sha256' => $this->sha256($asset->sha256, 'Answer Sheet asset hash'),
                'mime' => $this->nullableString($asset->mime),
                'bytes' => $asset->getAttribute('bytes') === null ? null : (int) $asset->getAttribute('bytes'),
            ];
        }
        usort($assets, static fn (array $left, array $right): int => [
            $left['page_no'] ?? 0,
            $left['kind'],
            $left['id'],
        ] <=> [
            $right['page_no'] ?? 0,
            $right['kind'],
            $right['id'],
        ]);

        return $assets;
    }

    /**
     * @param  list<array{id:int,kind:string,page_no:?int,sha256:string,mime:?string,bytes:?int}>  $assets
     * @return list<array{page_no:int,width:int,height:int,asset_sha256s:list<string>}>
     */
    private function pages(OmrAnswerSheetTemplateVersion $template, array $assets): array
    {
        $pages = [];
        foreach ($template->pages->sortBy([['page_no', 'asc'], ['id', 'asc']])->values() as $page) {
            $pageNo = $this->positiveId($page->page_no, 'Answer Sheet page number');
            $pageAssets = [];
            foreach ($assets as $asset) {
                if ($asset['page_no'] === $pageNo) {
                    $pageAssets[] = $asset['sha256'];
                }
            }
            $pages[] = [
                'page_no' => $pageNo,
                'width' => (int) $page->width,
                'height' => (int) $page->height,
                'asset_sha256s' => $pageAssets,
            ];
        }

        return $pages;
    }

    /** @return array<string, mixed> */
    private function profileMaterial(TestInstrumentVersion $profile, array $contract): array
    {
        $subtests = [];
        foreach ($profile->subtests->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $subtest) {
            $subtests[] = [
                'subtest_id' => (int) $subtest->id,
                'code' => (string) $subtest->code,
                'display_order' => (int) $subtest->display_order,
                'response_mode' => (string) $subtest->response_mode,
                'item_order_policy' => (string) $subtest->item_order_policy,
            ];
        }

        return [
            'profile_version_id' => (int) $profile->id,
            'instrument_id' => $this->positiveId($profile->test_instrument_id, 'Test Instrument'),
            'content_sha256' => (string) $contract['content_sha256'],
            'material_requirement' => (string) $profile->material_requirement,
            'administration_procedure_reference_sha256' => $profile->administration_procedure_reference === null
                ? null
                : hash('sha256', (string) $profile->administration_procedure_reference),
            'source_reference_sha256' => $profile->source_reference === null
                ? null
                : hash('sha256', (string) $profile->source_reference),
            'permitted_use_reference_sha256' => $profile->permitted_use_reference === null
                ? null
                : hash('sha256', (string) $profile->permitted_use_reference),
            'subtests' => $subtests,
        ];
    }

    /** @return array{sections:list<array<string, mixed>>,items:list<array<string, mixed>>} */
    private function intrinsicBookletItems(OmrQuestionBookletVersion $booklet, bool $strict = false): array
    {
        if (Schema::hasTable('omr_booklet_sections')) {
            $booklet->loadMissing(['sections', 'items.section']);
        }
        $rawSections = $booklet->relationLoaded('sections')
            ? $booklet->sections
            : (Schema::hasTable('omr_booklet_sections') ? $booklet->sections : collect());
        if ($rawSections->isEmpty()) {
            if ($strict) {
                throw new RuntimeException('The exact Booklet section inventory is required for a schema-3 material snapshot.');
            }
            $rawSections = collect([
                (new OmrBookletSection)->forceFill([
                    'id' => 1,
                    'booklet_version_id' => $booklet->id,
                    'booklet_section_uid' => '00000000-0000-4000-8000-000000000001',
                    'name' => 'General',
                    'display_order' => 1,
                    'expected_count' => count($booklet->items ?? []),
                    'option_tokens' => ['A', 'B', 'C', 'D'],
                ]),
            ]);
        }
        $sections = [];
        $sectionOrder = [];
        foreach ($rawSections->sortBy([['display_order', 'asc'], ['booklet_section_uid', 'asc']])->values() as $index => $section) {
            $uid = (string) $section->booklet_section_uid;
            if ($uid === '' || (int) $section->booklet_version_id !== (int) $booklet->id || isset($sectionOrder[$uid])) {
                throw new RuntimeException('The intrinsic Booklet section inventory contains a missing or duplicate identity.');
            }
            $sectionOrder[$uid] = $index;
            $sectionTokens = $strict
                ? $this->tokens($section->option_tokens, 'Intrinsic Booklet section option tokens')
                : array_values((array) $section->option_tokens);
            $sections[] = [
                'booklet_section_uid' => $uid,
                'name' => (string) $section->name,
                'display_order' => (int) $section->display_order,
                'expected_count' => (int) $section->expected_count,
                'option_tokens' => $sectionTokens,
            ];
        }

        $rawItems = $booklet->relationLoaded('items')
            ? $booklet->items
            : (Schema::hasTable('omr_booklet_items') ? $booklet->items : collect());
        $items = $rawItems->sortBy(static function ($item) use ($sectionOrder): array {
            return [
                $sectionOrder[(string) $item->booklet_section_uid] ?? PHP_INT_MAX,
                (int) $item->authored_position,
                (int) $item->id,
            ];
        })->values()->map(function ($item) use ($booklet, $sectionOrder, $strict): array {
            $sectionUid = $strict
                ? (string) $item->booklet_section_uid
                : (string) ($item->booklet_section_uid ?: '00000000-0000-4000-8000-000000000001');
            $itemUid = $strict
                ? (string) $item->booklet_item_uid
                : (string) ($item->booklet_item_uid ?: ('item-'.$item->authored_position));
            if (($strict && (int) ($item->booklet_version_id ?? 0) !== (int) $booklet->id)
                || (int) ($item->booklet_version_id ?? $booklet->id) !== (int) $booklet->id
                || $itemUid === '' || ! isset($sectionOrder[$sectionUid])) {
                throw new RuntimeException('The intrinsic Booklet item inventory contains a cross-version or missing item identity.');
            }
            if ($strict && ($item->key_required === null || $item->content_hash === null)) {
                throw new RuntimeException('The intrinsic Booklet item key disposition and content hash must be explicitly saved.');
            }
            $optionTokens = $strict
                ? $this->tokens($item->option_tokens, 'Intrinsic Booklet item option tokens')
                : array_values((array) ($item->option_tokens ?: ['A', 'B', 'C', 'D']));
            $contentHash = $strict
                ? $this->sha256($item->content_hash, 'Intrinsic Booklet item content hash')
                : $this->sha256($item->content_hash ?: hash('sha256', (string) $item->authored_position), 'Intrinsic Booklet item content hash');

            return [
                'booklet_item_uid' => $itemUid,
                'booklet_section_uid' => $sectionUid,
                'authored_position' => (int) $item->authored_position,
                'option_tokens' => $optionTokens,
                'key_required' => (bool) ($item->key_required ?? true),
                'content_sha256' => $contentHash,
            ];
        })->all();

        return ['sections' => $sections, 'items' => $items];
    }

    /** @return list<array<string, mixed>> */
    private function manifestItems(TestInstrumentVersion $profile, array $contract): array
    {
        $itemsById = [];
        foreach ($profile->subtests as $subtest) {
            foreach ($subtest->items as $item) {
                $itemsById[(int) $item->id] = [
                    'item_code' => (string) $item->item_code,
                    'local_number' => (string) $item->local_number,
                    'display_order' => (int) $item->display_order,
                ];
            }
        }

        return array_map(static fn (array $item): array => [
            ...$item,
            ...($itemsById[$item['item_id']] ?? []),
        ], $contract['profile_items']);
    }

    /** @return list<array<string, mixed>> */
    private function manifestBindings(array $bindings): array
    {
        return array_map(static fn (array $binding): array => [
            'page_no' => $binding['page_no'],
            'native_label' => $binding['native_label'],
            'global_position' => $binding['global_position'],
            'sheet_item_id' => $binding['sheet_item_id'],
            'item_id' => $binding['item_id'],
            'subtest_id' => $binding['subtest_id'],
            'option_tokens' => $binding['option_tokens'],
        ], $bindings);
    }

    private function sourceKind(OmrAnswerSheetTemplateVersion $template): string
    {
        $manifest = $template->authoring_manifest;
        $sourceKind = is_array($manifest) ? ($manifest['source_kind'] ?? null) : null;
        if (! is_string($sourceKind)
            || ! in_array($sourceKind, [OmrSourcePolicy::SYSTEM_DESIGNED, OmrSourcePolicy::UPLOADED_ORIGINAL], true)) {
            throw new RuntimeException('The compiled Answer Sheet must retain its exact supported source kind.');
        }

        return $sourceKind;
    }

    /** @return array<string|int, mixed> */
    private function requiredArray(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new RuntimeException($field.' must be persisted as an array.');
        }

        return $value;
    }

    /** @return list<string> */
    private function tokens(mixed $tokens, string $field): array
    {
        if (! is_array($tokens) || ! array_is_list($tokens) || $tokens === []) {
            throw new RuntimeException($field.' must be a non-empty ordered token list.');
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (! is_string($token) || $token === '' || trim($token) === '' || in_array($token, $normalized, true)) {
                throw new RuntimeException($field.' must contain exact, non-empty, unique string tokens.');
            }

            $normalized[] = $token;
        }

        return $normalized;
    }

    private function enumValue(mixed $value, string $field): string
    {
        $value = $value instanceof BackedEnum ? $value->value : $value;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException($field.' is missing.');
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function positiveId(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (int) $value < 1 || (string) (int) $value !== (string) $value) {
            throw new RuntimeException($field.' must be a persisted positive integer.');
        }

        return (int) $value;
    }

    private function sha256(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/i', $value) !== 1) {
            throw new RuntimeException($field.' must be a SHA-256 digest.');
        }

        return strtolower($value);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $entry): mixed => $this->canonicalize($entry), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) {
            $value[$key] = $this->canonicalize($entry);
        }

        return $value;
    }

    private function hashManifest(array $manifest): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($manifest),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
