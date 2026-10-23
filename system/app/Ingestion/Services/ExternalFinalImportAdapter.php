<?php

namespace App\Ingestion\Services;

use App\Models\Assessment\TestInstrumentVersion;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Validation\ValidationException;

final class ExternalFinalImportAdapter
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $selection
     * @return list<array{metric_code:string, typed_outputs:array{percentile_rank:string}, external_provenance:string}>
     */
    public function entries(array $payload, TestInstrumentVersion $profile, array $selection, int $testKitVersionId): array
    {
        $profile->loadMissing('metrics');
        $metrics = $profile->metrics->sortBy('id')->values();
        $metricMap = $metrics->mapWithKeys(fn ($metric): array => [strtoupper($metric->code) => (int) $metric->id])->all();
        $sourceMap = [];
        foreach (array_keys($metricMap) as $code) {
            $sourceMap[$code] = $code.'_source_reference';
        }
        $requiredCodes = $metrics
            ->filter(fn ($metric): bool => (bool) $metric->is_required)
            ->map(fn ($metric): string => strtoupper($metric->code))
            ->values()
            ->all();
        if (($selection['input_kind'] ?? null) !== 'external_final'
            || ($selection['scoring_mode'] ?? null) !== 'manual_converted'
            || ($selection['output_scale'] ?? null) !== 'percentile_rank'
            || (int) ($selection['profile_version_id'] ?? 0) !== (int) $profile->id
            || (int) ($selection['test_kit_version_id'] ?? 0) !== $testKitVersionId
            || ! $profile->isFinalOnly()
            || ($selection['metric_code_to_id'] ?? null) !== $metricMap
            || ($selection['source_header_map'] ?? null) !== $sourceMap
            || ($selection['required_metric_codes'] ?? null) !== $requiredCodes) {
            throw ValidationException::withMessages(['test_kit_version_id' => 'The staged external-final contract no longer matches its exact profile declaration.']);
        }

        $entries = [];
        foreach ($requiredCodes as $code) {
            $normalizedCode = SpreadsheetParser::normalizeHeader($code);
            $value = $payload[$normalizedCode] ?? null;
            $sourceReference = $payload[SpreadsheetParser::normalizeHeader($sourceMap[$code])] ?? null;
            if ($value === null || trim((string) $value) === '') {
                throw ValidationException::withMessages(['scores' => "Missing required percentile rank for {$code}."]);
            }
            if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
                throw ValidationException::withMessages(['scores' => "Percentile rank for {$code} must be numeric from 0 to 100."]);
            }
            try {
                $precision = (int) $profile->metrics->firstWhere('code', $this->metricCode($profile, $code))?->precision_scale;
                if (BigDecimal::of((string) $value)->getScale() > $precision) {
                    throw ValidationException::withMessages(['scores' => "Percentile rank for {$code} exceeds its declared precision."]);
                }
            } catch (NumberFormatException) {
                throw ValidationException::withMessages(['scores' => "Percentile rank for {$code} must be numeric."]);
            }
            if (! is_string($sourceReference) || trim($sourceReference) === '') {
                throw ValidationException::withMessages(['scores' => "A source reference is required for {$code}."]);
            }
            $entries[] = [
                'metric_code' => $this->metricCode($profile, $code),
                'typed_outputs' => ['percentile_rank' => (string) $value],
                'external_provenance' => trim($sourceReference),
            ];
        }

        return $entries;
    }

    private function metricCode(TestInstrumentVersion $profile, string $code): string
    {
        return (string) $profile->metrics->first(fn ($metric): bool => strtoupper($metric->code) === $code)?->code;
    }
}
