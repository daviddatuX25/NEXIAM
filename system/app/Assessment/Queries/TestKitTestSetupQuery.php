<?php

namespace App\Assessment\Queries;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TestKitTestSetupQuery
{
    public function __construct(private readonly TestInstrumentAccess $access, private readonly TestInstrumentProfileValidator $validator) {}

    /** @return array<string, mixed> */
    public function queryForInstrument(User $actor, int $instrumentId, ?int $versionId = null): array
    {
        $institutionId = $this->access->institutionId($actor);
        $instrumentModel = TestInstrument::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($instrumentId);
        $instrumentVersion = $versionId !== null
            ? TestInstrumentVersion::query()
                ->where('test_instrument_id', $instrumentModel->id)
                ->findOrFail($versionId)
            : ($instrumentModel->draftVersion ?? $instrumentModel->currentApprovedVersion);
        $isFinalOnly = $instrumentVersion?->isFinalOnly() ?? false;
        $normPackage = $isFinalOnly ? null : $this->resolveStandaloneNormPackage($institutionId, $instrumentId, $instrumentVersion?->form_code);
        $normVersion = $normPackage?->draftVersion ?? $normPackage?->currentApprovedVersion;
        $editable = $instrumentVersion !== null && $instrumentVersion->status === TestInstrumentVersion::STATUS_DRAFT;
        $readOnly = ! $editable;

        return [
            'test_kit' => null,
            'preparation' => null,
            'read_only' => $readOnly,
            'test_setup' => [
                'instrument' => $instrumentVersion === null ? null : $this->instrumentProjection($instrumentVersion),
                'norm' => $normVersion === null ? null : $this->normProjection($normVersion),
            ],
            'instrument_candidates' => $editable ? $this->instrumentCandidates($institutionId, null) : [],
            'norm_candidates' => $editable && ! $isFinalOnly ? $this->normCandidates($institutionId, null) : [],
            'constructs' => AptitudeArea::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(static fn (AptitudeArea $area): array => [
                    'id' => (int) $area->id,
                    'code' => (string) $area->code,
                    'name' => (string) $area->name,
                ])->all(),
            'metric_codes' => $instrumentVersion?->metrics
                ->map(static fn ($metric): array => [
                    'code' => (string) $metric->code,
                    'name' => (string) $metric->name,
                    'required_output_scales' => array_values((array) $metric->required_output_scales),
                ])->all() ?? [],
            'actions' => [
                'save' => $editable ? route('setup.test-instruments.setup.update', ['instrument' => $instrumentId], absolute: false) : null,
                'save_preparation' => null,
                'instrument_successor' => ! $editable && $instrumentVersion !== null && $instrumentVersion->status === TestInstrumentVersion::STATUS_APPROVED
                    ? route('setup.test-instruments.versions.successor', [
                        'instrument' => $instrumentVersion->test_instrument_id,
                        'version' => $instrumentVersion->id,
                    ], absolute: false)
                    : null,
                'norm_successor' => null,
                'back' => route('setup.test-instruments.index', absolute: false),
            ],
            'kit_context' => null,
        ];
    }

    private function resolveStandaloneNormPackage(
        int $institutionId,
        int $instrumentId,
        ?string $formCode = null,
    ): ?AssessmentNormPackage {
        $direct = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->where('code', 'INSTRUMENT_'.$instrumentId)
            ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
            ->first();
        if ($direct !== null) {
            return $direct;
        }

        $versionIds = TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrumentId)
            ->pluck('id');
        if ($versionIds->isNotEmpty()) {
            $linkedNormVersionId = TestKitPreparation::query()
                ->whereIn('test_instrument_version_id', $versionIds)
                ->whereNotNull('norm_package_version_id')
                ->orderByDesc('id')
                ->value('norm_package_version_id');
            if ($linkedNormVersionId !== null) {
                $linked = AssessmentNormPackage::query()
                    ->where('institution_id', $institutionId)
                    ->whereHas('versions', static fn (Builder $q): Builder => $q->whereKey($linkedNormVersionId))
                    ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
                    ->first();
                if ($linked !== null) {
                    return $linked;
                }
            }
        }

        if ($formCode !== null && $formCode !== '') {
            return AssessmentNormPackage::query()
                ->where('institution_id', $institutionId)
                ->whereHas('versions', static fn (Builder $q): Builder => $q->where('form_code', $formCode))
                ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
                ->first();
        }

        return null;
    }

    public function query(User $actor, int $testKitId, ?int $sealedVersionId = null): array
    {
        $institutionId = $this->access->institutionId($actor);
        $kit = TestKit::query()->where('institution_id', $institutionId)->findOrFail($testKitId);
        $preparation = $kit->openPreparation()->first();
        $sealedVersion = null;

        if ($sealedVersionId !== null) {
            $sealedVersion = TestKitVersion::query()
                ->where('test_kit_id', $kit->id)
                ->whereKey($sealedVersionId)
                ->firstOrFail();
            $preparation = TestKitPreparation::query()
                ->where('test_kit_id', $kit->id)
                ->where('assembled_test_kit_version_id', $sealedVersion->id)
                ->first();
        } elseif ($preparation === null) {
            $sealedVersion = TestKitVersion::query()
                ->where('test_kit_id', $kit->id)
                ->orderByDesc('version_number')
                ->first();
            if ($sealedVersion !== null) {
                $preparation = TestKitPreparation::query()
                    ->where('test_kit_id', $kit->id)
                    ->where('assembled_test_kit_version_id', $sealedVersion->id)
                    ->first();
            }
        }

        $editable = $sealedVersion === null && $preparation !== null && $preparation->state === TestKitPreparation::STATE_PREPARING;
        $instrumentVersion = $editable
            ? $this->selectedInstrument($institutionId, $kit, $preparation)
            : $this->sealedInstrument($institutionId, $kit, $sealedVersion);
        $isFinalOnly = $instrumentVersion?->isFinalOnly() ?? false;
        $normVersion = $editable && ! $isFinalOnly ? $this->selectedNorm($institutionId, $kit, $preparation) : null;
        $instrument = $instrumentVersion === null ? null : $this->instrumentProjection($instrumentVersion);
        $norm = $isFinalOnly ? null : ($editable
            ? ($normVersion === null ? null : $this->normProjection($normVersion))
            : $this->sealedNormSnapshot($preparation));
        $readOnly = ! $editable
            || ($instrumentVersion !== null && $instrumentVersion->status !== TestInstrumentVersion::STATUS_DRAFT)
            || ($normVersion !== null && $normVersion->status !== AssessmentNormPackageVersion::STATUS_DRAFT);
        $canSave = $editable && ! $readOnly;

        return [
            'test_kit' => [
                'id' => (int) $kit->id,
                'code' => (string) $kit->code,
                'name' => (string) $kit->name,
            ],
            'preparation' => $preparation === null ? null : [
                'id' => (int) $preparation->id,
                'revision' => (int) $preparation->revision,
                'state' => (string) $preparation->state,
                'assembly_context_sha256' => $preparation->assembly_context_sha256,
                'selections' => [
                    'test_instrument_version_id' => $preparation->test_instrument_version_id === null ? null : (int) $preparation->test_instrument_version_id,
                    'norm_package_version_id' => $preparation->norm_package_version_id === null ? null : (int) $preparation->norm_package_version_id,
                    'answer_sheet_draft_id' => $preparation->answer_sheet_draft_id === null ? null : (int) $preparation->answer_sheet_draft_id,
                    'template_version_id' => $preparation->template_version_id === null ? null : (int) $preparation->template_version_id,
                    'booklet_version_id' => $preparation->booklet_version_id === null ? null : (int) $preparation->booklet_version_id,
                    'booklet_set_id' => $preparation->booklet_set_id === null ? null : (int) $preparation->booklet_set_id,
                ],
            ],
            'read_only' => $readOnly,
            'test_setup' => [
                'instrument' => $instrument,
                'norm' => $norm,
            ],
            'instrument_candidates' => $editable ? $this->instrumentCandidates($institutionId, $kit) : [],
            'norm_candidates' => $editable && ! $isFinalOnly ? $this->normCandidates($institutionId, $kit) : [],
            'constructs' => AptitudeArea::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(static fn (AptitudeArea $area): array => [
                    'id' => (int) $area->id,
                    'code' => (string) $area->code,
                    'name' => (string) $area->name,
                ])->all(),
            'metric_codes' => $instrumentVersion?->metrics
                ->map(static fn ($metric): array => [
                    'code' => (string) $metric->code,
                    'name' => (string) $metric->name,
                    'required_output_scales' => array_values((array) $metric->required_output_scales),
                ])->all() ?? [],
            'actions' => [
                'save' => $canSave ? route('setup.exam-materials.test-kits.test-setup.update', [
                    'testKit' => $kit->id,
                    'test_kit_id' => $kit->id,
                    'preparation_id' => $editable ? $preparation->id : null,
                ], absolute: false) : null,
                'save_preparation' => $editable ? route('setup.exam-materials.test-kits.preparation.update', [
                    'testKit' => $kit->id,
                    'test_kit_id' => $kit->id,
                    'preparation_id' => $preparation->id,
                ], absolute: false) : null,
                'instrument_successor' => $editable && $instrumentVersion !== null && $instrumentVersion->status !== TestInstrumentVersion::STATUS_DRAFT
                    ? route('setup.test-instruments.versions.successor', [
                        'instrument' => $instrumentVersion->test_instrument_id,
                        'version' => $instrumentVersion->id,
                        'test_kit_id' => $kit->id,
                        'preparation_id' => $preparation->id,
                    ], absolute: false)
                    : null,
                'norm_successor' => $editable && $normVersion !== null && $normVersion->status !== AssessmentNormPackageVersion::STATUS_DRAFT
                    ? route('setup.norm-packages.edit', [
                        'normPackage' => $normVersion->norm_package_id,
                        'test_kit_id' => $kit->id,
                        'preparation_id' => $preparation->id,
                    ], absolute: false)
                    : null,
                'back' => route('setup.exam-materials.test-kits.show', [
                    'testKit' => $kit->id,
                    ...($sealedVersion === null ? [] : ['version' => $sealedVersion->id]),
                ], absolute: false),
            ],
            'kit_context' => [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => $editable ? (int) $preparation->id : null,
                'test_kit_version_id' => $sealedVersion === null ? null : (int) $sealedVersion->id,
                'back_url' => route('setup.exam-materials.test-kits.show', [
                    'testKit' => $kit->id,
                    ...($sealedVersion === null ? [] : ['version' => $sealedVersion->id]),
                ], absolute: false),
            ],
        ];
    }

    private function selectedInstrument(int $institutionId, TestKit $kit, TestKitPreparation $preparation): ?TestInstrumentVersion
    {
        if ($preparation->test_instrument_version_id === null) {
            return null;
        }

        $version = TestInstrumentVersion::query()
            ->with(['instrument', 'subtests.items', 'metrics.inputs.inputMetric', 'metrics.subtest'])
            ->whereKey($preparation->test_instrument_version_id)
            ->whereHas('instrument', static fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $kit->is_synthetic))
            ->firstOrFail();

        return $version;
    }

    private function sealedInstrument(int $institutionId, TestKit $kit, ?TestKitVersion $sealedVersion): ?TestInstrumentVersion
    {
        if ($sealedVersion === null || $sealedVersion->test_instrument_version_id === null) {
            return null;
        }

        return TestInstrumentVersion::query()
            ->with(['instrument', 'subtests.items', 'metrics.inputs.inputMetric', 'metrics.subtest'])
            ->whereKey($sealedVersion->test_instrument_version_id)
            ->whereHas('instrument', static fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $kit->is_synthetic))
            ->firstOrFail();
    }

    private function selectedNorm(int $institutionId, TestKit $kit, TestKitPreparation $preparation): ?AssessmentNormPackageVersion
    {
        if ($preparation->norm_package_version_id === null) {
            return null;
        }

        return AssessmentNormPackageVersion::query()
            ->with(['package', 'tables'])
            ->whereKey($preparation->norm_package_version_id)
            ->whereHas('package', static fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->where('is_synthetic', $kit->is_synthetic))
            ->firstOrFail();
    }

    /** @return array<string, mixed>|null */
    private function sealedNormSnapshot(?TestKitPreparation $preparation): ?array
    {
        $context = $preparation?->assembly_context_json;
        $contextDigest = $preparation?->assembly_context_sha256;
        if (! is_array($context)
            || ! is_string($contextDigest)
            || ! hash_equals(
                $contextDigest,
                hash('sha256', json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            )) {
            return null;
        }

        $snapshot = $context['norm'] ?? null;
        if (! is_array($snapshot)
            || ! is_numeric($snapshot['id'] ?? null)
            || ! is_numeric($snapshot['package_id'] ?? null)
            || ! is_numeric($snapshot['lock_version'] ?? null)
            || ! is_array($snapshot['metadata'] ?? null)
            || ! is_array($snapshot['tables'] ?? null)) {
            return null;
        }
        $normalizedTables = $this->sortAssociativeKeys($snapshot['tables']);
        $tablesDigest = hash(
            'sha256',
            json_encode($normalizedTables, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if (! is_string($snapshot['tables_sha256'] ?? null)
            || ! hash_equals($snapshot['tables_sha256'], $tablesDigest)) {
            return null;
        }

        return [
            'package_id' => (int) $snapshot['package_id'],
            'version_id' => (int) $snapshot['id'],
            'version_number' => null,
            'lock_version' => (int) $snapshot['lock_version'],
            'status' => (string) ($snapshot['status'] ?? ''),
            'content_sha256' => $snapshot['content_sha256'] ?? null,
            'tables_sha256' => $snapshot['tables_sha256'],
            'source_reviewed_at' => $snapshot['source_reviewed_at'] ?? null,
            'approved_at' => $snapshot['approved_at'] ?? null,
            'package' => $snapshot['metadata'],
            'tables' => array_values($normalizedTables),
        ];
    }

    /** @return array<string, mixed> */
    private function instrumentProjection(TestInstrumentVersion $version): array
    {
        $normalized = $this->validator->normalizeVersion($version);

        return [
            'id' => (int) $version->instrument->id,
            'version_id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'lock_version' => (int) $version->lock_version,
            'status' => (string) $version->status,
            'content_sha256' => $this->validator->digest($normalized),
            'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
            'approved_at' => $version->approved_at?->toISOString(),
            'profile' => [
                'code' => (string) $version->instrument->code,
                'display_name' => (string) $version->display_name,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
                'material_requirement' => $version->material_requirement,
                'administration_procedure_reference' => $version->administration_procedure_reference,
            ],
            'subtests' => $normalized['subtests'],
            'metrics' => $normalized['metrics'],
            'profile_metadata' => ['is_final_only' => $version->isFinalOnly()],
        ];
    }

    /** @return array<string, mixed> */
    private function normProjection(AssessmentNormPackageVersion $version): array
    {
        return [
            'package_id' => (int) $version->norm_package_id,
            'version_id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'lock_version' => (int) $version->lock_version,
            'status' => (string) $version->status,
            'content_sha256' => $version->content_sha256,
            'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
            'approved_at' => $version->approved_at?->toISOString(),
            'package' => [
                'code' => (string) $version->package->code,
                'display_name' => (string) $version->display_name,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'applicable_population' => $version->applicable_population,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
            ],
            'tables' => $version->tables->map(static fn (AssessmentNormTable $table): array => [
                'metric_code' => (string) $table->metric_code,
                'output_scale' => (string) $table->output_scale,
                'intervals' => array_values((array) $table->intervals_json),
            ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function instrumentCandidates(int $institutionId, ?TestKit $kit): array
    {
        return TestInstrumentVersion::query()
            ->with('instrument')
            ->whereIn('status', [TestInstrumentVersion::STATUS_DRAFT, TestInstrumentVersion::STATUS_APPROVED])
            ->whereHas('instrument', static fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->when($kit !== null, static fn (Builder $query): Builder => $query->where('is_synthetic', $kit->is_synthetic)))
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (TestInstrumentVersion $version): array => [
                'instrument_id' => (int) $version->test_instrument_id,
                'version_id' => (int) $version->id,
                'code' => (string) $version->instrument->code,
                'display_name' => (string) $version->display_name,
                'version_number' => (int) $version->version_number,
                'lock_version' => (int) $version->lock_version,
                'status' => (string) $version->status,
                'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
                'approved_at' => $version->approved_at?->toISOString(),
                'content_sha256' => $version->content_sha256,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function normCandidates(int $institutionId, ?TestKit $kit): array
    {
        return AssessmentNormPackageVersion::query()
            ->with('package')
            ->whereIn('status', [AssessmentNormPackageVersion::STATUS_DRAFT, AssessmentNormPackageVersion::STATUS_APPROVED])
            ->whereHas('package', static fn (Builder $query): Builder => $query
                ->where('institution_id', $institutionId)
                ->when($kit !== null, static fn (Builder $query): Builder => $query->where('is_synthetic', $kit->is_synthetic)))
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (AssessmentNormPackageVersion $version): array => [
                'package_id' => (int) $version->norm_package_id,
                'version_id' => (int) $version->id,
                'code' => (string) $version->package->code,
                'display_name' => (string) $version->display_name,
                'version_number' => (int) $version->version_number,
                'lock_version' => (int) $version->lock_version,
                'status' => (string) $version->status,
                'source_reviewed_at' => $version->source_reviewed_at?->toISOString(),
                'approved_at' => $version->approved_at?->toISOString(),
                'content_sha256' => $version->content_sha256,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'applicable_population' => $version->applicable_population,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
            ])->all();
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortAssociativeKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortAssociativeKeys($item);
            }
        }

        return $value;
    }
}
