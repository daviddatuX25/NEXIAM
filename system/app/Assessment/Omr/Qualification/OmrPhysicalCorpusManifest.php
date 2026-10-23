<?php

namespace App\Assessment\Omr\Qualification;

final readonly class OmrPhysicalCorpusManifest
{
    public const CONTRACT = 'nexiam.omr.qualification-corpus.v1';

    public const MODES = ['development', 'production'];

    private const REQUIRED_ADVERSARIAL_CONDITIONS = [
        'glare',
        'motion_blur',
        'jpeg_compression',
        'difficult_background',
        'rotated',
        'exif_orientation',
        'erased_mark',
        'double_mark',
        'missing_fiducial',
        'covered_fiducial',
        'partial_sheet',
        'wrong_sheet',
    ];

    private const DOCUMENT_BOUND_ADVERSARIAL_CONDITIONS = [
        'glare',
        'motion_blur',
        'jpeg_compression',
        'difficult_background',
        'rotated',
        'exif_orientation',
        'erased_mark',
        'double_mark',
        'cropped_edge',
        'competing_boundary',
        'partial_sheet',
        'wrong_sheet',
    ];

    private const ADVERSARIAL_OUTCOMES = [
        'glare' => 'reject',
        'motion_blur' => 'reject',
        'jpeg_compression' => 'reject',
        'difficult_background' => 'reject',
        'rotated' => 'reject',
        'exif_orientation' => 'reject',
        'erased_mark' => 'review',
        'double_mark' => 'review',
        'missing_fiducial' => 'reject',
        'covered_fiducial' => 'reject',
        'partial_sheet' => 'reject',
        'wrong_sheet' => 'reject',
        'cropped_edge' => 'reject',
        'competing_boundary' => 'reject',
    ];

    private const POSITIVE_MEDIA = ['dark_black_pen', 'hb_2_pencil'];

    private const POSITIVE_MARK_STRENGTHS = ['normal', 'faint'];

    private const POSITIVE_LIGHTING = ['even', 'directional_shadow'];

    private const POSITIVE_GEOMETRY = ['near_square', 'mild_perspective'];

    /**
     * @param  array{id:string,version:string,config_hash:string}  $strategy
     * @param  list<array<string, mixed>>  $captures
     * @param  list<string>  $coverageIssues
     */
    private function __construct(
        public string $sourcePath,
        public string $corpusId,
        public string $mode,
        public array $strategy,
        public array $captures,
        public array $coverageIssues,
        public string $sourceKind = 'system_designed',
        public int $pageCount = 1,
    ) {}

    public static function fromPath(string $path): self
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved)) {
            throw new \InvalidArgumentException('qualification_manifest_missing');
        }
        try {
            $decoded = json_decode((string) file_get_contents($resolved), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('qualification_manifest_invalid_json', 0, $exception);
        }
        if (! is_array($decoded) || ($decoded['contract'] ?? null) !== self::CONTRACT) {
            throw new \InvalidArgumentException('qualification_manifest_contract_invalid');
        }

        $corpusId = trim((string) ($decoded['corpus_id'] ?? ''));
        $mode = (string) ($decoded['mode'] ?? '');
        $sourceKind = (string) ($decoded['source_kind'] ?? 'system_designed');
        $pageCount = isset($decoded['page_count']) ? (int) $decoded['page_count'] : 1;
        $strategy = $decoded['strategy'] ?? null;
        $strategyKeys = is_array($strategy) ? array_keys($strategy) : [];
        sort($strategyKeys);
        if ($corpusId === '' || ! in_array($mode, self::MODES, true)
            || ! in_array($sourceKind, ['system_designed', 'uploaded_original'], true)
            || $pageCount < 1 || $pageCount > 4
            || ! is_array($strategy)
            || $strategyKeys !== ['config_hash', 'id', 'version']
            || trim((string) $strategy['id']) === ''
            || trim((string) $strategy['version']) === ''
            || preg_match('/^[a-f0-9]{64}$/', (string) $strategy['config_hash']) !== 1) {
            throw new \InvalidArgumentException('qualification_manifest_header_invalid');
        }

        $rows = $decoded['captures'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new \InvalidArgumentException('qualification_manifest_captures_invalid');
        }
        $captures = [];
        $ids = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('qualification_capture_invalid');
            }
            $capture = self::capture($row, dirname($resolved), $sourceKind, $pageCount);
            if (isset($ids[$capture['id']])) {
                throw new \InvalidArgumentException('qualification_capture_id_duplicate');
            }
            $ids[$capture['id']] = true;
            $captures[] = $capture;
        }
        usort($captures, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        return new self(
            sourcePath: $resolved,
            corpusId: $corpusId,
            mode: $mode,
            strategy: [
                'id' => (string) $strategy['id'],
                'version' => (string) $strategy['version'],
                'config_hash' => (string) $strategy['config_hash'],
            ],
            captures: $captures,
            coverageIssues: self::coverageIssues($mode, $captures, $sourceKind, $pageCount),
            sourceKind: $sourceKind,
            pageCount: $pageCount,
        );
    }

    public function productionCoverageComplete(): bool
    {
        return $this->mode === 'production' && $this->coverageIssues === [];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function capture(array $row, string $directory, string $sourceKind = 'system_designed', int $pageCount = 1): array
    {
        $id = trim((string) ($row['id'] ?? ''));
        $relativePath = (string) ($row['path'] ?? '');
        $sha256 = strtolower((string) ($row['sha256'] ?? ''));
        $classification = (string) ($row['classification'] ?? '');
        $expectedOutcome = (string) ($row['expected_outcome'] ?? '');
        $method = (string) ($row['method'] ?? '');
        $medium = $row['medium'] ?? null;
        $conditions = $row['conditions'] ?? [];
        $allowedMethods = $sourceKind === 'uploaded_original'
            ? ['supplier_blank', 'supplier_hand_marked']
            : ['pre_shaded', 'hand_shaded'];
        if ($id === '' || str_contains($id, '/') || $relativePath === ''
            || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
            || ! in_array($classification, ['positive', 'adversarial', 'regression_only'], true)
            || ! in_array($expectedOutcome, ['exact', 'review', 'reject'], true)
            || ! in_array($method, $allowedMethods, true)
            || ! is_array($conditions) || ! array_is_list($conditions)
            || filter_var($row['page_no'] ?? null, FILTER_VALIDATE_INT) === false
            || (int) $row['page_no'] < 1
            || (int) $row['page_no'] > max(4, $pageCount)
            || ($row['synthetic'] ?? false) === true
            || (string) ($row['source'] ?? '') !== 'real_capture') {
            throw new \InvalidArgumentException("qualification_capture_invalid:{$id}");
        }
        if (($classification === 'positive' && $expectedOutcome !== 'exact')
            || ($classification === 'adversarial' && $expectedOutcome === 'exact')) {
            throw new \InvalidArgumentException("qualification_capture_outcome_invalid:{$id}");
        }
        if ($classification === 'adversarial') {
            $adversarialConditions = array_values(array_filter(
                array_map('strval', $conditions),
                static fn (string $condition): bool => isset(self::ADVERSARIAL_OUTCOMES[$condition]),
            ));
            if (count($adversarialConditions) !== 1
                || self::ADVERSARIAL_OUTCOMES[$adversarialConditions[0]] !== $expectedOutcome) {
                throw new \InvalidArgumentException("qualification_capture_outcome_invalid:{$id}");
            }
        }
        if (in_array($method, ['hand_shaded', 'supplier_hand_marked'], true) && ! in_array($medium, ['hb_2_pencil', 'dark_black_pen'], true)) {
            throw new \InvalidArgumentException("qualification_capture_medium_invalid:{$id}");
        }
        if (in_array($method, ['pre_shaded', 'supplier_blank'], true) && $medium !== null) {
            throw new \InvalidArgumentException("qualification_capture_medium_invalid:{$id}");
        }

        $absolutePath = str_starts_with($relativePath, DIRECTORY_SEPARATOR)
            ? realpath($relativePath)
            : realpath($directory.DIRECTORY_SEPARATOR.$relativePath);
        if ($absolutePath === false || ! is_file($absolutePath)) {
            throw new \InvalidArgumentException("qualification_capture_file_missing:{$id}");
        }
        $actualHash = hash_file('sha256', $absolutePath);
        if (! is_string($actualHash) || ! hash_equals($sha256, $actualHash)) {
            throw new \InvalidArgumentException("qualification_capture_hash_mismatch:{$id}");
        }
        $image = @getimagesize($absolutePath);
        $mime = strtolower((string) ($image['mime'] ?? ''));
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException("qualification_capture_mime_invalid:{$id}");
        }

        return [
            ...$row,
            'id' => $id,
            'absolute_path' => $absolutePath,
            'sha256' => $sha256,
            'mime' => $mime,
            'classification' => $classification,
            'expected_outcome' => $expectedOutcome,
            'method' => $method,
            'medium' => $medium,
            'page_no' => (int) $row['page_no'],
            'conditions' => array_values(array_unique(array_map('strval', $conditions))),
        ];
    }

    /** @param list<array<string, mixed>> $captures @return list<string> */
    private static function coverageIssues(string $mode, array $captures, string $sourceKind = 'system_designed', int $pageCount = 1): array
    {
        if ($mode !== 'production') {
            return ['development_corpus_not_qualification_evidence'];
        }
        if ($sourceKind === 'uploaded_original') {
            return self::uploadedCoverageIssues($captures, $pageCount);
        }
        $positive = array_values(array_filter($captures, static fn (array $row): bool => $row['classification'] === 'positive'));
        $adversarial = array_values(array_filter($captures, static fn (array $row): bool => $row['classification'] === 'adversarial'));
        $issues = [];
        if (count($positive) !== 48) {
            $issues[] = 'positive_capture_count_must_be_48';
        }
        if (count($adversarial) !== 12) {
            $issues[] = 'adversarial_capture_count_must_be_12';
        }
        $qualificationHashes = array_map(
            static fn (array $row): string => $row['sha256'],
            [...$positive, ...$adversarial],
        );
        if (count(array_unique($qualificationHashes)) !== 60) {
            $issues[] = 'qualification_capture_bytes_must_be_distinct';
        }
        if (array_filter(
            $positive,
            static fn (array $row): bool => ! is_array($row['expected_vector'] ?? null)
                || ($row['expected_vector'] ?? []) === []
                || ! is_array($row['expected_identity'] ?? null)
                || ($row['expected_identity'] ?? []) === [],
        ) !== []) {
            $issues[] = 'positive_truth_labels_incomplete';
        }

        $devices = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string) ($row['device_class'] ?? ''),
            $positive,
        ))));
        sort($devices);
        $actualCombinations = [];
        $positiveMetadataValid = count($devices) === 3;
        foreach ($positive as $row) {
            $values = [
                (string) ($row['device_class'] ?? ''),
                (string) ($row['medium'] ?? ''),
                (string) ($row['mark_strength'] ?? ''),
                (string) ($row['lighting'] ?? ''),
                (string) ($row['geometry'] ?? ''),
            ];
            if (! in_array($values[0], $devices, true)
                || ! in_array($values[1], self::POSITIVE_MEDIA, true)
                || ! in_array($values[2], self::POSITIVE_MARK_STRENGTHS, true)
                || ! in_array($values[3], self::POSITIVE_LIGHTING, true)
                || ! in_array($values[4], self::POSITIVE_GEOMETRY, true)) {
                $positiveMetadataValid = false;
            }
            $key = implode('|', $values);
            $actualCombinations[$key] = ($actualCombinations[$key] ?? 0) + 1;
        }
        $expectedCombinations = [];
        foreach ($devices as $device) {
            foreach (self::POSITIVE_MEDIA as $medium) {
                foreach (self::POSITIVE_MARK_STRENGTHS as $strength) {
                    foreach (self::POSITIVE_LIGHTING as $lighting) {
                        foreach (self::POSITIVE_GEOMETRY as $geometry) {
                            $expectedCombinations[implode('|', [$device, $medium, $strength, $lighting, $geometry])] = 1;
                        }
                    }
                }
            }
        }
        ksort($actualCombinations);
        ksort($expectedCombinations);
        if (! $positiveMetadataValid || $actualCombinations !== $expectedCombinations) {
            $issues[] = 'positive_matrix_incomplete';
        }
        $conditionCounts = array_fill_keys(self::REQUIRED_ADVERSARIAL_CONDITIONS, 0);
        foreach ($adversarial as $row) {
            foreach ($row['conditions'] as $condition) {
                if (array_key_exists($condition, $conditionCounts)) {
                    $conditionCounts[$condition]++;
                }
            }
        }
        if (array_values($conditionCounts) !== array_fill(0, count(self::REQUIRED_ADVERSARIAL_CONDITIONS), 1)) {
            $issues[] = 'adversarial_matrix_incomplete';
        }

        return array_values(array_unique($issues));
    }

    /** @param list<array<string, mixed>> $captures @return list<string> */
    private static function uploadedCoverageIssues(array $captures, int $pageCount): array
    {
        $positive = array_values(array_filter($captures, static fn (array $row): bool => $row['classification'] === 'positive'));
        $adversarial = array_values(array_filter($captures, static fn (array $row): bool => $row['classification'] === 'adversarial'));
        $issues = [];
        $devices = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string) ($row['device_class'] ?? ''),
            $positive,
        ))));
        sort($devices);
        if (count($devices) !== 3) {
            $issues[] = 'supplier_device_coverage_incomplete';
        }
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pageBlank = array_values(array_filter(
                $positive,
                static fn (array $row): bool => (int) $row['page_no'] === $pageNo && $row['method'] === 'supplier_blank',
            ));
            $pageHand = array_values(array_filter(
                $positive,
                static fn (array $row): bool => (int) $row['page_no'] === $pageNo && $row['method'] === 'supplier_hand_marked',
            ));
            if ($pageBlank === []) {
                $issues[] = "supplier_blank_missing_page_{$pageNo}";
            }
            if ($pageHand === []) {
                $issues[] = "supplier_hand_marked_missing_page_{$pageNo}";
            }
        }
        $conditionCounts = array_fill_keys(self::DOCUMENT_BOUND_ADVERSARIAL_CONDITIONS, 0);
        foreach ($adversarial as $row) {
            foreach ($row['conditions'] as $condition) {
                if (array_key_exists($condition, $conditionCounts)) {
                    $conditionCounts[$condition]++;
                }
            }
        }
        if (array_filter($conditionCounts, static fn (int $count): bool => $count < 1) !== []) {
            $issues[] = 'adversarial_matrix_incomplete';
        }

        return array_values(array_unique($issues));
    }
}
