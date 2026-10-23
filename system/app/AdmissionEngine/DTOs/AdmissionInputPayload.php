<?php

namespace App\AdmissionEngine\DTOs;

use App\Admission\Support\GwaScaleNormalizer;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Models\Admission\AdmissionAttempt;
use InvalidArgumentException;

final readonly class AdmissionInputPayload
{
    /** @var array<string, float> */
    public array $aptitudePercentiles;

    /** @var array<string, string|null> */
    public array $aptitudeScales;

    /**
     * @param  array<string, float>  $aptitudePercentiles
     * @param  array<string, string|null>  $aptitudeScales
     */
    public function __construct(
        public int $attemptId,
        public int $institutionId,
        public int $academicCycleId,
        public ?float $gwa,
        array $aptitudePercentiles,
        array $aptitudeScales = [],
    ) {
        $normalizedPercentiles = [];
        foreach ($aptitudePercentiles as $code => $value) {
            $canonicalCode = is_string($code) ? BenchmarkInputSchema::canonicalConstructCode($code) : null;
            if ($canonicalCode === null || ! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 100) {
                throw new InvalidArgumentException('Model input may contain only approved in-range aptitude measures.');
            }
            if (array_key_exists($canonicalCode, $normalizedPercentiles) && $normalizedPercentiles[$canonicalCode] !== (float) $value) {
                throw new InvalidArgumentException('Model input contains conflicting aliases for one aptitude measure.');
            }
            $normalizedPercentiles[$canonicalCode] = (float) $value;
        }

        $normalizedScales = [];
        foreach ($aptitudeScales as $code => $scale) {
            $canonicalCode = is_string($code) ? BenchmarkInputSchema::canonicalConstructCode($code) : null;
            if ($canonicalCode === null || ($scale !== null && ! in_array($scale, ['percentile_rank', 'percentile', 'legacy'], true))) {
                throw new InvalidArgumentException('Model input may contain only supported typed aptitude scales.');
            }
            if (array_key_exists($canonicalCode, $normalizedScales) && $normalizedScales[$canonicalCode] !== $scale) {
                throw new InvalidArgumentException('Model input contains conflicting aliases for one aptitude scale.');
            }
            $normalizedScales[$canonicalCode] = $scale;
        }

        $this->aptitudePercentiles = $normalizedPercentiles;
        $this->aptitudeScales = $normalizedScales;
    }

    /** @param array<string, mixed> $scoreSeries */
    public static function fromAttempt(AdmissionAttempt $attempt, array $scoreSeries): self
    {
        $aptitudes = [];
        $aptitudeScales = [];
        foreach (($scoreSeries['series'] ?? $scoreSeries) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $code = $entry['area_code'] ?? null;
            $canonicalCode = is_string($code) ? BenchmarkInputSchema::canonicalConstructCode($code) : null;
            if ($canonicalCode === null || ! in_array($canonicalCode, BenchmarkInputSchema::canonicalAptitudeCodes(), true)) {
                continue;
            }
            if (($entry['available'] ?? true) === false) {
                continue;
            }
            $rawScale = $entry['scale'] ?? $entry['scale_type'] ?? null;
            if (! in_array($rawScale, ['percentile_rank', 'percentile', 'legacy'], true)) {
                continue;
            }
            $candidateScore = $entry['percentile'] ?? ($rawScale === 'percentile_rank' && is_numeric($entry['converted_score'] ?? null)
                ? $entry['converted_score']
                : null);
            if ($candidateScore === null) {
                continue;
            }
            if (! is_numeric($candidateScore) || ! is_finite((float) $candidateScore)
                || (float) $candidateScore < 0.0 || (float) $candidateScore > 100.0) {
                throw new InvalidArgumentException('Aptitude percentile inputs must be finite values from 0 through 100.');
            }
            if (array_key_exists($canonicalCode, $aptitudes)
                && ($aptitudes[$canonicalCode] !== (float) $candidateScore || $aptitudeScales[$canonicalCode] !== $rawScale)) {
                throw new InvalidArgumentException('Score series contains conflicting construct aliases.');
            }
            $aptitudes[$canonicalCode] = (float) $candidateScore;
            $aptitudeScales[$canonicalCode] = $rawScale;
        }

        $normalizedGwa = GwaScaleNormalizer::fromAttempt($attempt)['gwa'];

        return new self(
            (int) $attempt->id,
            (int) $attempt->institution_id,
            (int) $attempt->academic_cycle_id,
            $normalizedGwa,
            $aptitudes,
            $aptitudeScales,
        );
    }

    public function digest(): string
    {
        return hash('sha256', json_encode($this, JSON_THROW_ON_ERROR));
    }
}
