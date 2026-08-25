<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

    use function array_filter;
    use function array_map;
    use function array_values;
    use function count;
    use function hash;
    use function is_array;
    use function json_encode;
    use function ksort;
    use function max;
    use function min;
    use function preg_match;
    use function sprintf;
    use function str_starts_with;

/**
 * Typed geometry-projection failure. The error codes named in the shared
 * verification cut (tasks 35.4–35.10) are the pair/analysis gates:
 * `geometry_projection_missing`, `geometry_projection_stale`,
 * `pair_geometry_coverage_mismatch`, and `pair_option_set_mismatch`.
 */
final class OmrTemplateProjectionException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($errorCode.': '.$message);
    }
}

/**
 * Result of projecting one immutable template version's review manifest into
 * the version-owned relational geometry (pages, areas, questions, options).
 * `areas` and `questions` carry the augmented-manifest shapes the activation
 * stores on the version; `projectedQuestions` carries the persisted row
 * identity used to link booklet questions.
 */
final class GeometryProjectionResult
{
    /**
     * @param  array<string, mixed>  $areas
     * @param  array<string, mixed>  $questions
     * @param  list<array<string, mixed>>  $projectedQuestions
     */
    public function __construct(
        public readonly string $projectionHash,
        public readonly int $pageCount,
        public readonly int $areaCount,
        public readonly int $questionCount,
        public readonly int $optionCount,
        public readonly array $areas,
        public readonly array $questions,
        public readonly array $projectedQuestions,
        public readonly bool $reused,
    ) {
    }
}

/**
 * The single owner of version-owned template geometry persistence. Both the
 * legacy activation (`ActivateOmrConfiguration`) and both canonical compile
 * paths call `project()` inside their transactions; identical manifests
 * replay idempotently, and a manifest that differs from already-projected
 * rows is rejected as stale instead of being silently re-projected.
 */
final class OmrTemplateGeometryProjector
{
    public const PROJECTION_SOURCE = 'template_geometry_projection';

    /**
     * Persist (or reuse) the version-owned projection for one review
     * manifest. Idempotent: an identical existing projection is returned
     * untouched; a differing existing projection throws
     * `geometry_projection_stale`.
     *
     * @param  array<string, mixed>  $reviewManifest
     */
    public function project(OmrAnswerSheetTemplateVersion $version, array $reviewManifest): GeometryProjectionResult
    {
        $projection = $this->deriveProjection($reviewManifest, $version);
        $hash = $this->hashProjection($projection);

        $existing = $this->loadProjection($version);
        if ($existing !== null) {
            $this->assertCurrentRows($existing, $hash);

            return $this->resultFrom($projection, $existing, $hash, reused: true);
        }

        $this->persistProjection($version, $reviewManifest, $projection, $hash);

        return $this->resultFrom($projection, $this->loadProjection($version), $hash, reused: false);
    }

    /**
     * Assert the version carries a complete, current projection. Throws
     * `geometry_projection_missing` when no projection rows exist and
     * `geometry_projection_stale` when the stored rows no longer match the
     * version's review manifest. Used by the pair activation and analysis
     * gates.
     */
    public function currentFor(OmrAnswerSheetTemplateVersion $version): GeometryProjectionResult
    {
        $manifest = is_array($version->review_manifest_json) ? $version->review_manifest_json : null;
        $existing = $this->loadProjection($version);

        if ($manifest === null || $existing === null) {
            throw new OmrTemplateProjectionException(
                'geometry_projection_missing',
                sprintf('Template version %d has no version-owned geometry projection.', (int) $version->id),
            );
        }

        $projection = $this->deriveProjection($manifest, $version);
        $hash = $this->hashProjection($projection);
        $this->assertCurrentRows($existing, $hash);

        return $this->resultFrom($projection, $existing, $hash, reused: true);
    }

    /**
     * Normalized bounding union over normalized regions.
     *
     * @param  list<array{x: float, y: float, w: float, h: float}>  $regions
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function unionRegions(array $regions): array
    {
        return [
            'x' => min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'y' => min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
            'w' => max(array_map(static fn (array $region): float => (float) $region['x'] + $region['w'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'h' => max(array_map(static fn (array $region): float => (float) $region['y'] + $region['h'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
        ];
    }

    /**
     * Derive the canonical projection payload (pages, areas, questions with
     * declared options) from a review manifest. Scorable labels must carry
     * their semantic area code (`AREA_Q_NNN`) and be contiguous from 1 per
     * area; identity fields are not scorable geometry.
     *
     * @param  array<string, mixed>  $reviewManifest
     * @return array{
     *     pages: list<array{page_no: int, width: int, height: int}>,
     *     areas: list<array{code: string, max_items: int, display_order: int, region: array{x: float, y: float, w: float, h: float}}>,
     *     questions: list<array{area_code: string, question_no: int, page: int, region: array{x: float, y: float, w: float, h: float}, options: list<array{code: string, display_order: int, region: array{x: float, y: float, w: float, h: float}, native_label: string}>}>,
     * }
     */
    private function deriveProjection(array $reviewManifest, OmrAnswerSheetTemplateVersion $version): array
    {
        $questions = [];

        foreach ((array) ($reviewManifest['fields'] ?? []) as $field) {
            $label = (string) ($field['label'] ?? '');

            if (str_starts_with($label, 'APPLICANT_NUMBER_') || str_starts_with($label, 'BIRTHDATE_')) {
                continue;
            }

            if (preg_match('/^([A-Z0-9_]+)_Q_(\d{1,3})$/', $label, $matches) !== 1) {
                throw new RuntimeException(sprintf('Native manifest contains an undeclared field label: %s.', $label));
            }

            $questions[$matches[1]][(int) $matches[2]]['options'][] = $field;
        }

        if ($questions === []) {
            throw new RuntimeException('Native manifest contains no scorable questions.');
        }

        ksort($questions);
        $areas = [];
        $projectedQuestions = [];

        foreach ($questions as $areaCode => $areaQuestions) {
            ksort($areaQuestions);
            $expectedNo = 1;
            $questionShapes = [];

            foreach ($areaQuestions as $questionNo => $question) {
                if ($questionNo !== $expectedNo) {
                    throw new RuntimeException(sprintf(
                        'Native manifest question positions must be contiguous per area; area %s jumps from %d to %d.',
                        $areaCode,
                        $expectedNo - 1,
                        $questionNo,
                    ));
                }
                $expectedNo++;

                $options = [];
                foreach ($question['options'] as $optionIndex => $field) {
                    $options[] = [
                        'code' => (string) ($field['option'] ?? ''),
                        'display_order' => $optionIndex + 1,
                        'region' => $this->normalizedRegion($field),
                        'native_label' => (string) ($field['label'] ?? ''),
                    ];
                }

                $questionShapes[] = [
                    'area_code' => $areaCode,
                    'question_no' => $questionNo,
                    'page' => (int) ($question['options'][0]['page'] ?? 1),
                    'region' => self::unionRegions(array_map(
                        static fn (array $option): array => $option['region'],
                        $options,
                    )),
                    'options' => $options,
                ];
            }

            $projectedQuestions = [...$projectedQuestions, ...$questionShapes];
            $areas[] = [
                'code' => $areaCode,
                'max_items' => count($questionShapes),
                'display_order' => count($areas) + 1,
                'region' => self::unionRegions(array_map(
                    static fn (array $question): array => $question['region'],
                    $questionShapes,
                )),
            ];
        }

        $pages = [];
        foreach ((array) ($reviewManifest['pages'] ?? []) as $pageData) {
            $dimensions = is_array($pageData['page_dimensions'] ?? null) ? $pageData['page_dimensions'] : [];
            $pages[] = [
                'page_no' => (int) ($pageData['page_no'] ?? 1),
                'width' => (int) ($dimensions['width'] ?? $version->page_width),
                'height' => (int) ($dimensions['height'] ?? $version->page_height),
            ];
        }

        return [
            'pages' => $pages,
            'areas' => $areas,
            'questions' => $projectedQuestions,
        ];
    }

    /** @return array{x: float, y: float, w: float, h: float} */
    private function normalizedRegion(array $field): array
    {
        $region = is_array($field['region'] ?? null) ? $field['region'] : [];

        return [
            'x' => (float) ($region['x'] ?? 0),
            'y' => (float) ($region['y'] ?? 0),
            'w' => (float) ($region['w'] ?? 0),
            'h' => (float) ($region['h'] ?? 0),
        ];
    }

    /** @param  array<string, mixed>  $projection */
    private function hashProjection(array $projection): string
    {
        $payload = [
            'pages' => $projection['pages'],
            'areas' => array_map(static fn (array $area): array => [
                'code' => $area['code'],
                'max_items' => $area['max_items'],
                'display_order' => $area['display_order'],
                'region' => $area['region'],
            ], $projection['areas']),
            'questions' => array_map(static fn (array $question): array => [
                'area_code' => $question['area_code'],
                'question_no' => $question['question_no'],
                'page' => $question['page'],
                'region' => $question['region'],
                'options' => array_map(static fn (array $option): array => [
                    'code' => $option['code'],
                    'display_order' => $option['display_order'],
                    'region' => $option['region'],
                ], $question['options']),
            ], $projection['questions']),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{
     *     pages: Collection<int, OmrAnswerSheetPage>,
     *     areas: Collection<int, OmrTemplateArea>,
     * }|null
     */
    private function loadProjection(OmrAnswerSheetTemplateVersion $version): ?array
    {
        $areas = OmrTemplateArea::query()
            ->where('template_version_id', $version->id)
            ->with(['aptitudeArea', 'questions.options'])
            ->orderBy('display_order')
            ->get();

        if ($areas->isEmpty()) {
            return null;
        }

        $pages = OmrAnswerSheetPage::query()
            ->where('template_version_id', $version->id)
            ->orderBy('page_no')
            ->get();

        return ['pages' => $pages, 'areas' => $areas];
    }

    /** @param  array<string, mixed>  $existing */
    private function assertCurrentRows(array $existing, string $hash): void
    {
        foreach ($existing['areas'] as $area) {
            if ((string) $area->projection_manifest_hash !== $hash) {
                throw new OmrTemplateProjectionException(
                    'geometry_projection_stale',
                    sprintf('Template area %d projection is stale; its manifest hash differs from the version manifest.', (int) $area->id),
                );
            }
            foreach ($area->questions as $question) {
                if ((string) $question->projection_manifest_hash !== $hash) {
                    throw new OmrTemplateProjectionException(
                        'geometry_projection_stale',
                        sprintf('Template question %d projection is stale; its manifest hash differs from the version manifest.', (int) $question->id),
                    );
                }
                foreach ($question->options as $option) {
                    if ((string) $option->projection_manifest_hash !== $hash) {
                        throw new OmrTemplateProjectionException(
                            'geometry_projection_stale',
                            sprintf('Template option %d projection is stale; its manifest hash differs from the version manifest.', (int) $option->id),
                        );
                    }
                }
            }
        }

        foreach ($existing['pages'] as $page) {
            if ((string) $page->page_manifest_hash !== $hash) {
                throw new OmrTemplateProjectionException(
                    'geometry_projection_stale',
                    sprintf('Template page %d projection is stale; its manifest hash differs from the version manifest.', (int) $page->id),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $existing
     */
    private function resultFrom(array $projection, array $existing, string $hash, bool $reused): GeometryProjectionResult
    {
        $projectedQuestions = [];
        $optionCount = 0;

        foreach ($existing['areas'] as $area) {
            $code = (string) ($area->aptitudeArea?->code ?? '');
            foreach ($area->questions->sortBy('question_no')->values() as $question) {
                $options = $question->options->sortBy('display_order')->values()
                    ->map(static fn (OmrTemplateQuestionOption $option): array => [
                        'code' => (string) $option->code,
                        'display_order' => (int) $option->display_order,
                        'region' => [
                            'x' => (float) $option->region_x,
                            'y' => (float) $option->region_y,
                            'w' => (float) $option->region_w,
                            'h' => (float) $option->region_h,
                        ],
                    ])->all();
                $optionCount += count($options);

                $projectedQuestions[] = [
                    'template_question_id' => (int) $question->id,
                    'area_id' => (int) $area->aptitude_area_id,
                    'area_code' => $code,
                    'area_name' => (string) ($area->aptitudeArea?->name ?? 'Aptitude area'),
                    'question_no' => (int) $question->question_no,
                    'page_no' => (int) $question->page_no,
                    'region' => [
                        'x' => (float) $question->region_x,
                        'y' => (float) $question->region_y,
                        'w' => (float) $question->region_w,
                        'h' => (float) $question->region_h,
                    ],
                    'options' => $options,
                ];
            }
        }

        return new GeometryProjectionResult(
            projectionHash: $hash,
            pageCount: count($existing['pages']),
            areaCount: count($existing['areas']),
            questionCount: count($projectedQuestions),
            optionCount: $optionCount,
            areas: $projection['areas'],
            questions: $projection['questions'],
            projectedQuestions: $projectedQuestions,
            reused: $reused,
        );
    }

    /**
     * @param  array<string, mixed>  $projection
     */
    private function persistProjection(
        OmrAnswerSheetTemplateVersion $version,
        array $reviewManifest,
        array $projection,
        string $hash,
    ): void {
        $catalogAreas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('code');
        $pages = $projection['pages'];
        if ($pages === []) {
            $pages = [['page_no' => 1, 'width' => (int) $version->page_width, 'height' => (int) $version->page_height]];
        }

        $augmentedManifest = [
            ...$reviewManifest,
            'areas' => $projection['areas'],
            'questions' => $projection['questions'],
        ];

        foreach ($pages as $pageData) {
            OmrAnswerSheetPage::query()->create([
                'template_version_id' => $version->id,
                'page_no' => (int) $pageData['page_no'],
                'width' => (int) $pageData['width'],
                'height' => (int) $pageData['height'],
                'page_manifest_json' => $augmentedManifest,
                'page_manifest_hash' => $hash,
            ]);
        }

        foreach ($projection['areas'] as $areaData) {
            $catalogArea = $catalogAreas->get($areaData['code']);
            if (! $catalogArea instanceof AptitudeArea) {
                throw new RuntimeException(sprintf('Native manifest area %s is not an active aptitude area.', $areaData['code']));
            }

            $area = OmrTemplateArea::query()->create([
                'template_version_id' => $version->id,
                'aptitude_area_id' => $catalogArea->id,
                'display_order' => $areaData['display_order'],
                'max_items' => $areaData['max_items'],
                ...$this->regionColumns($areaData['region']),
                'projection_manifest_hash' => $hash,
                'projection_source' => self::PROJECTION_SOURCE,
            ]);

            foreach (array_values(array_filter($projection['questions'], static fn (array $question): bool => $question['area_code'] === $areaData['code'])) as $questionData) {
                $question = OmrTemplateQuestion::query()->create([
                    'template_area_id' => $area->id,
                    'page_no' => (int) ($questionData['page'] ?? 1),
                    'question_no' => $questionData['question_no'],
                    ...$this->regionColumns($questionData['region']),
                    'projection_manifest_hash' => $hash,
                    'projection_source' => self::PROJECTION_SOURCE,
                ]);

                foreach ($questionData['options'] as $optionData) {
                    OmrTemplateQuestionOption::query()->create([
                        'template_question_id' => $question->id,
                        'code' => $optionData['code'],
                        'display_order' => $optionData['display_order'],
                        ...$this->regionColumns($optionData['region']),
                        'projection_manifest_hash' => $hash,
                        'projection_source' => self::PROJECTION_SOURCE,
                    ]);
                }
            }
        }
    }

    /** @return array{region_x: float, region_y: float, region_w: float, region_h: float} */
    private function regionColumns(array $region): array
    {
        return [
            'region_x' => $region['x'],
            'region_y' => $region['y'],
            'region_w' => $region['w'],
            'region_h' => $region['h'],
        ];
    }
}
