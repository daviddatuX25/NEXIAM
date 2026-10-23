<?php

namespace Tests\Fixtures\Omr;

use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrSyntheticEvidenceCase;

use function count;

/**
 * Fixture helpers for selecting and asserting deterministic synthetic
 * evidence cases across tests and demo seeders.
 */
final class SyntheticEvidenceFixture
{
    /**
     * @return list<string>
     */
    public static function allCases(): array
    {
        return OmrSyntheticEvidenceCase::ALL_CASES;
    }

    /**
     * Maps the given canonical labels (e.g. ['VER_Q_001', 'VER_Q_002', ...])
     * cyclically to each of the eight required synthetic cases.
     *
     * @param  list<string>  $labels
     * @return array<string, string>
     */
    public static function cyclicCaseMap(array $labels): array
    {
        $cases = self::allCases();
        $caseCount = count($cases);
        $map = [];

        foreach ($labels as $index => $label) {
            $map[$label] = $cases[$index % $caseCount];
        }

        return $map;
    }

    /**
     * Build an explicit case configuration with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function caseSpec(string $case, array $overrides = []): array
    {
        return [
            'case' => OmrSyntheticEvidenceCase::normalizeCaseName($case),
            ...$overrides,
        ];
    }

    /**
     * Standard four-option ABCD geometry fixture.
     *
     * @return list<OmrOptionGeometry>
     */
    public static function standardOptions(): array
    {
        return [
            new OmrOptionGeometry('A', new OmrRegion(0.10, 0.20, 0.04, 0.02), 1),
            new OmrOptionGeometry('B', new OmrRegion(0.28, 0.20, 0.04, 0.02), 2),
            new OmrOptionGeometry('C', new OmrRegion(0.46, 0.20, 0.04, 0.02), 3),
            new OmrOptionGeometry('D', new OmrRegion(0.64, 0.20, 0.04, 0.02), 4),
        ];
    }
}
