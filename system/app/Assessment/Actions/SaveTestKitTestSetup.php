<?php

namespace App\Assessment\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaveTestKitTestSetup
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly SaveTestInstrumentDraft $saveInstrumentDraft,
        private readonly TestInstrumentProfileValidator $profileValidator,
        private readonly SaveAssessmentNormDraft $saveNormDraft,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{instrument_id: int, instrument_version_id: int, norm_package_id: ?int, norm_version_id: ?int}
     */
    public function handleStandalone(User $actor, int $instrumentId, array $input): array
    {
        $institutionId = $this->access->institutionId($actor);
        $requestId = trim((string) ($input['request_id'] ?? $input['idempotency_key'] ?? ''));
        if (! Str::isUuid($requestId)) {
            throw ValidationException::withMessages(['request_id' => 'Provide a valid request ID before saving.']);
        }

        $instrumentInput = is_array($input['instrument'] ?? null) ? $input['instrument'] : [];
        $normInput = is_array($input['norm'] ?? null) ? $input['norm'] : [];
        $selectedInstrumentId = $this->nullableId($instrumentInput['id'] ?? null, 'instrument.id');
        $instrumentVersionId = $this->nullableId($instrumentInput['version_id'] ?? null, 'instrument.version_id');
        $instrumentExpectedRevision = $this->nullableId($instrumentInput['expected_revision'] ?? null, 'instrument.expected_revision');
        if ($selectedInstrumentId !== $instrumentId
            || $instrumentVersionId === null
            || $instrumentExpectedRevision === null
            || ! is_array($instrumentInput['profile'] ?? null)) {
            throw ValidationException::withMessages(['instrument' => 'Reload the instrument Test Setup before saving.']);
        }

        return DB::transaction(function () use (
            $actor,
            $institutionId,
            $instrumentId,
            $instrumentVersionId,
            $instrumentExpectedRevision,
            $requestId,
            $instrumentInput,
            $normInput,
        ): array {
            TestInstrument::query()
                ->where('institution_id', $institutionId)
                ->whereKey($instrumentId)
                ->lockForUpdate()
                ->firstOrFail();

            $instrumentPayload = [
                'profile' => $instrumentInput['profile'],
                'subtests' => $instrumentInput['subtests'] ?? [],
                'metrics' => $instrumentInput['metrics'] ?? [],
            ];
            $savedVersion = $this->saveInstrumentDraft->handle(
                $actor,
                $instrumentId,
                $instrumentVersionId,
                $instrumentExpectedRevision,
                $requestId,
                $instrumentPayload,
                true,
            );

            if (! $savedVersion->isFinalOnly() && (is_array($normInput['package'] ?? null) || array_key_exists('tables', $normInput))) {
                $normPayload = [];
                if (is_array($normInput['package'] ?? null)) {
                    $normPayload['package'] = $normInput['package'];
                }
                if (array_key_exists('tables', $normInput) && is_array($normInput['tables'])) {
                    $normPayload['tables'] = $normInput['tables'];
                }
                if ($normPayload !== []) {
                    $this->saveNormDraft->handle(
                        $actor,
                        $this->nullableId($normInput['package_id'] ?? null, 'norm.package_id'),
                        $this->nullableId($normInput['version_id'] ?? null, 'norm.version_id'),
                        $this->nullableId($normInput['expected_revision'] ?? null, 'norm.expected_revision'),
                        $requestId,
                        $normPayload,
                        true,
                    );
                }
            }
            $result = [
                'instrument_id' => $instrumentId,
                'instrument_version_id' => (int) $savedVersion->id,
                'norm_package_id' => null,
                'norm_version_id' => null,
            ];
            $this->audit->handle('assessment.test-instrument.setup_saved', [
                ...$result,
                'instrument_revision' => (int) $savedVersion->lock_version,
                'is_final_only' => $savedVersion->isFinalOnly(),
            ], (string) $actor->id);

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{test_kit_id: int, preparation_id: int, instrument_id: int, instrument_version_id: int, norm_package_id: ?int, norm_version_id: ?int, preparation_revision: int}
     */
    public function handle(User $actor, int $testKitId, array $input): array
    {
        $institutionId = $this->access->institutionId($actor);
        $preparationId = (int) ($input['preparation_id'] ?? 0);
        $expectedPreparationRevision = filter_var($input['expected_preparation_revision'] ?? null, FILTER_VALIDATE_INT);
        $requestId = trim((string) ($input['request_id'] ?? $input['idempotency_key'] ?? ''));
        if (! Str::isUuid($requestId) || $preparationId < 1 || $expectedPreparationRevision === false || $expectedPreparationRevision < 1) {
            throw ValidationException::withMessages(['preparation' => 'Reload the current Test Setup before saving.']);
        }

        $instrumentInput = is_array($input['instrument'] ?? null) ? $input['instrument'] : [];
        $normInput = is_array($input['norm'] ?? null) ? $input['norm'] : [];
        $instrumentId = $this->nullableId($instrumentInput['id'] ?? null, 'instrument.id');
        $instrumentVersionId = $this->nullableId($instrumentInput['version_id'] ?? null, 'instrument.version_id');
        $instrumentRevision = $this->nullableId($instrumentInput['expected_revision'] ?? null, 'instrument.expected_revision');
        $normPackageId = $this->nullableId($normInput['package_id'] ?? null, 'norm.package_id');
        $normVersionId = $this->nullableId($normInput['version_id'] ?? null, 'norm.version_id');
        $normRevision = $this->nullableId($normInput['expected_revision'] ?? null, 'norm.expected_revision');

        if (($instrumentId === null) !== ($instrumentVersionId === null)
            || ($instrumentId === null && $instrumentRevision !== null)
            || ($normPackageId === null) !== ($normVersionId === null)
            || ($normPackageId === null && $normRevision !== null)) {
            throw ValidationException::withMessages(['selection' => 'Choose the exact selected draft before saving.']);
        }

        $normalizedRequest = [
            'test_kit_id' => $testKitId,
            'preparation_id' => $preparationId,
            'expected_preparation_revision' => (int) $expectedPreparationRevision,
            'instrument' => $instrumentInput,
            'norm' => $normInput,
        ];
        $requestDigest = hash('sha256', json_encode($normalizedRequest, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $actor,
                $institutionId,
                $testKitId,
                $preparationId,
                $expectedPreparationRevision,
                $requestId,
                $requestDigest,
                $instrumentInput,
                $normInput,
                $instrumentId,
                $instrumentVersionId,
                $instrumentRevision,
                $normPackageId,
                $normVersionId,
                $normRevision,
            ): array {
                $freshActor = User::query()->findOrFail($actor->getKey());
                $kit = TestKit::query()
                    ->where('institution_id', $institutionId)
                    ->whereKey($testKitId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($this->access->institutionId($freshActor) !== (int) $kit->institution_id) {
                    throw ValidationException::withMessages(['authorization' => 'Test Setup access is no longer available.']);
                }
                $preparation = TestKitPreparation::query()
                    ->where('test_kit_id', $kit->id)
                    ->whereKey($preparationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $receipt = $this->idempotency->claim(
                    $freshActor,
                    'test_kit.save_test_setup',
                    'preparation:'.$preparation->id,
                    [
                        'test_kit_id' => (int) $kit->id,
                        'preparation_id' => (int) $preparation->id,
                        'expected_preparation_revision' => (int) $expectedPreparationRevision,
                        'request_digest' => $requestDigest,
                    ],
                    $requestId,
                );
                $receiptPayload = $receipt->payload;
                $priorResult = is_array($receiptPayload) ? ($receiptPayload['test_kit_test_setup_result'] ?? null) : null;
                if ($priorResult !== null) {
                    return $this->receiptResult($priorResult, (int) $kit->id, (int) $preparation->id);
                }

                if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
                    throw ValidationException::withMessages(['preparation' => 'Only the open preparation can be edited.']);
                }

                if ((int) $preparation->revision !== (int) $expectedPreparationRevision) {
                    throw ValidationException::withMessages(['expected_preparation_revision' => 'This preparation changed. Reload it before saving.']);
                }
                $finalOnlyRequested = is_array($instrumentInput['metrics'] ?? null)
                    && $instrumentInput['metrics'] !== []
                    && collect($instrumentInput['metrics'])->every(static fn (mixed $metric): bool => is_array($metric) && ($metric['rule_kind'] ?? null) === 'external_final');
                if ((int) ($preparation->test_instrument_version_id ?? 0) !== (int) ($instrumentVersionId ?? 0)
                    || (! $finalOnlyRequested && (int) ($preparation->norm_package_version_id ?? 0) !== (int) ($normVersionId ?? 0))) {
                    throw ValidationException::withMessages(['selection' => 'The selected profile or conversion tables changed. Reload Test Setup and review the exact selection.']);
                }

                $profile = $instrumentInput['profile'] ?? null;
                if (! is_array($profile)) {
                    throw ValidationException::withMessages(['instrument.profile' => 'Provide the normalized instrument profile.']);
                }
                $instrumentPayload = [
                    'profile' => $profile,
                    'subtests' => $instrumentInput['subtests'] ?? [],
                    'metrics' => $instrumentInput['metrics'] ?? [],
                ];
                $instrumentVersion = $this->saveInstrumentDraft->handle(
                    $freshActor,
                    $instrumentId,
                    $instrumentVersionId,
                    $instrumentRevision,
                    $requestId,
                    $instrumentPayload,
                    true,
                );
                $instrument = TestInstrument::query()
                    ->where('institution_id', $institutionId)
                    ->whereKey($instrumentVersion->test_instrument_id)
                    ->firstOrFail();
                if ((bool) $instrument->is_synthetic !== (bool) $kit->is_synthetic) {
                    throw ValidationException::withMessages(['instrument' => 'The instrument provenance does not match this Test Kit.']);
                }

                $isFinalOnly = $instrumentVersion->isFinalOnly();
                $normVersion = null;
                if (! $isFinalOnly) {
                    $normPackageData = is_array($normInput['package'] ?? null)
                        ? $normInput['package']
                        : array_intersect_key($normInput, array_flip([
                            'code', 'name', 'display_name', 'publisher_name', 'form_code', 'edition',
                            'language', 'applicable_population', 'source_reference', 'permitted_use_reference',
                        ]));
                    if (! isset($normPackageData['display_name']) && isset($normPackageData['name'])) {
                        $normPackageData['display_name'] = $normPackageData['name'];
                    }
                    $normPackageData['is_synthetic'] = (bool) $kit->is_synthetic;
                    if ($normPackageId !== null) {
                        $normPackage = AssessmentNormPackage::query()
                            ->where('institution_id', $institutionId)
                            ->whereKey($normPackageId)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $exactNormVersion = AssessmentNormPackageVersion::query()
                            ->where('norm_package_id', $normPackage->id)
                            ->whereKey($normVersionId)
                            ->lockForUpdate()
                            ->firstOrFail();
                        if ((bool) $normPackage->is_synthetic !== (bool) $kit->is_synthetic
                            || (int) $exactNormVersion->lock_version !== $normRevision) {
                            throw ValidationException::withMessages(['norm' => 'The exact selected conversion-table draft changed or has different provenance. Reload Test Setup.']);
                        }
                    }
                    $normSaveInput = ['package' => $normPackageData];
                    if (array_key_exists('tables', $normInput)) {
                        $normSaveInput['tables'] = $normInput['tables'];
                    }
                    $normVersion = $this->saveNormDraft->handle(
                        $freshActor, $normPackageId, $normVersionId, $normRevision, $requestId, $normSaveInput, true,
                    );
                    $normPackage = AssessmentNormPackage::query()
                        ->where('institution_id', $institutionId)
                        ->whereKey($normVersion->norm_package_id)
                        ->firstOrFail();
                    if ((bool) $normPackage->is_synthetic !== (bool) $kit->is_synthetic) {
                        throw ValidationException::withMessages(['norm' => 'The conversion-table provenance does not match this Test Kit.']);
                    }
                    $this->assertNormCompatibility($instrumentVersion, $normVersion);
                }
                $instrumentDigest = $this->profileValidator->digest($this->profileValidator->normalizeVersion($instrumentVersion));

                if ($preparation->test_instrument_version_id !== $instrumentVersion->id
                    || ($normVersion !== null && $preparation->norm_package_version_id !== $normVersion->id)
                    || ($isFinalOnly && $preparation->norm_package_version_id !== null)) {
                    $preparation->composition_revision = ($preparation->composition_revision ?? 1) + 1;
                }
                $preparation->test_instrument_version_id = $instrumentVersion->id;
                $preparation->norm_package_version_id = $normVersion?->id;
                $preparation->revision++;
                $preparation->updated_by_user_id = $freshActor->id;
                $preparation->save();

                $result = [
                    'test_kit_id' => (int) $kit->id,
                    'preparation_id' => (int) $preparation->id,
                    'instrument_id' => (int) $instrumentVersion->test_instrument_id,
                    'instrument_version_id' => (int) $instrumentVersion->id,
                    'norm_package_id' => $normVersion === null ? null : (int) $normVersion->norm_package_id,
                    'norm_version_id' => $normVersion === null ? null : (int) $normVersion->id,
                    'preparation_revision' => (int) $preparation->revision,
                ];
                $receiptPayload['test_kit_test_setup_result'] = $result;
                $receipt->forceFill(['payload' => $receiptPayload])->save();
                $this->audit->handle('assessment.test_kit.test_setup_saved', [
                    ...$result,
                    'instrument_revision' => (int) $instrumentVersion->lock_version,
                    'norm_revision' => $normVersion?->lock_version,
                    'instrument_sha256' => $instrumentDigest,
                    'norm_sha256' => $normVersion?->content_sha256,
                    'request_sha256' => $requestDigest,
                    'is_final_only' => $isFinalOnly,
                ], (string) $freshActor->id);

                return $result;
            });
        } catch (RuntimeException $exception) {
            if (! str_contains($exception->getMessage(), 'already used with a different request payload')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['request_id' => 'This request ID was already used for different content. Refresh Test Setup before retrying.']);
        }
    }

    private function assertNormCompatibility(TestInstrumentVersion $instrumentVersion, AssessmentNormPackageVersion $normVersion): void
    {
        $instrumentVersion->loadMissing('metrics');
        $metrics = $instrumentVersion->metrics->keyBy('code');

        foreach ($normVersion->tables as $table) {
            $metric = $metrics->get($table->metric_code);
            if ($metric === null) {
                throw ValidationException::withMessages(['norm.tables' => "Conversion table metric '{$table->metric_code}' is not declared by the selected profile."]);
            }

            $requestedScales = array_values((array) $metric->required_output_scales);
            if ($requestedScales !== [] && ! in_array($table->output_scale, $requestedScales, true)) {
                throw ValidationException::withMessages(['norm.tables' => "Conversion table scale '{$table->output_scale}' is not requested for metric '{$table->metric_code}'."]);
            }
            if ($normVersion->status === AssessmentNormPackageVersion::STATUS_APPROVED) {
                $this->assertDecimalGridCoverage($metric, $table);
            }
        }
    }

    private function assertDecimalGridCoverage(TestInstrumentMetric $metric, AssessmentNormTable $table): void
    {
        $intervals = is_array($table->intervals_json) ? $table->intervals_json : [];
        if ($intervals === []) {
            return;
        }

        $precision = max(0, (int) $metric->precision_scale);
        $rows = [];
        try {
            foreach ($intervals as $interval) {
                if (! is_array($interval)
                    || ! array_key_exists('min_score', $interval)
                    || ! array_key_exists('max_score', $interval)) {
                    throw new \InvalidArgumentException;
                }
                $minimum = BigDecimal::of((string) $interval['min_score'])
                    ->toScale($precision, RoundingMode::Unnecessary);
                $maximum = BigDecimal::of((string) $interval['max_score'])
                    ->toScale($precision, RoundingMode::Unnecessary);
                if ($minimum->compareTo($maximum) > 0) {
                    throw new \InvalidArgumentException;
                }
                $rows[] = [$minimum, $maximum];
            }
        } catch (\InvalidArgumentException|NumberFormatException|RoundingNecessaryException) {
            throw ValidationException::withMessages([
                'norm.tables' => "Conversion table for '{$metric->code}' is not expressed on its declared {$precision}-place score grid.",
            ]);
        }

        usort($rows, static fn (array $left, array $right): int => $left[0]->compareTo($right[0]));
        $step = BigDecimal::of($precision === 0 ? '1' : '0.'.str_repeat('0', $precision - 1).'1');
        $previousMaximum = null;
        foreach ($rows as [$minimum, $maximum]) {
            if ($previousMaximum !== null
                && ($minimum->compareTo($previousMaximum) <= 0
                    || $minimum->compareTo($previousMaximum->plus($step)) !== 0)) {
                throw ValidationException::withMessages([
                    'norm.tables' => "Conversion table for '{$metric->code}' does not cover its declared {$precision}-place score grid without overlap or gaps.",
                ]);
            }
            $previousMaximum = $maximum;
        }
    }

    private function nullableId(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw ValidationException::withMessages([$field => 'Choose a valid saved identity.']);
        }

        return (int) $id;
    }

    /** @param mixed $result @return array{test_kit_id: int, preparation_id: int, instrument_id: int, instrument_version_id: int, norm_package_id: ?int, norm_version_id: ?int, preparation_revision: int} */
    private function receiptResult(mixed $result, int $testKitId, int $preparationId): array
    {
        $normPackageId = is_array($result) ? ($result['norm_package_id'] ?? null) : null;
        $normVersionId = is_array($result) ? ($result['norm_version_id'] ?? null) : null;
        if (! is_array($result)
            || (int) ($result['test_kit_id'] ?? 0) !== $testKitId
            || (int) ($result['preparation_id'] ?? 0) !== $preparationId
            || ! is_numeric($result['instrument_id'] ?? null)
            || ! is_numeric($result['instrument_version_id'] ?? null)
            || (($normPackageId === null) !== ($normVersionId === null))
            || ($normPackageId !== null && (! is_numeric($normPackageId) || ! is_numeric($normVersionId)))
            || ! is_numeric($result['preparation_revision'] ?? null)) {
            throw new RuntimeException('The Test Setup command receipt has an invalid result identity.');
        }

        return [
            'test_kit_id' => $testKitId,
            'preparation_id' => $preparationId,
            'instrument_id' => (int) $result['instrument_id'],
            'instrument_version_id' => (int) $result['instrument_version_id'],
            'norm_package_id' => $normPackageId === null ? null : (int) $normPackageId,
            'norm_version_id' => $normVersionId === null ? null : (int) $normVersionId,
            'preparation_revision' => (int) $result['preparation_revision'],
        ];
    }
}
