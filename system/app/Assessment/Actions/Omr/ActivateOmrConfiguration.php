<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletQuestion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function hash;
use function json_encode;
use function ksort;
use function max;
use function min;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * Activates one native OMR configuration from the pinned bundle. All
 * relational geometry is written as a projection of the derived manifest in
 * one transaction. Staff-authored booklets (questions already present) are
 * validated instead of key-generated.
 */
final class ActivateOmrConfiguration
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(
        OmrAnswerSheetTemplateVersion $templateVersion,
        OmrQuestionBookletVersion $bookletVersion,
        User $actor,
    ): OmrTemplateBookletPair {
        return DB::transaction(function () use ($templateVersion, $bookletVersion, $actor): OmrTemplateBookletPair {
            $template = OmrAnswerSheetTemplateVersion::query()
                ->lockForUpdate()
                ->with('template')
                ->findOrFail($templateVersion->id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($bookletVersion->id);

            if ($template->status !== OmrAnswerSheetTemplateVersion::STATUS_DRAFT
                || $booklet->status !== OmrQuestionBookletVersion::STATUS_DRAFT) {
                throw new RuntimeException('Only draft OMR template and booklet versions may be activated.');
            }

            $bundle = NativeBundle::load((string) $template->template->code, (int) $template->version);
            $derived = $this->manifestDeriver->derive($bundle);
            $manifest = $this->withProjections($derived['manifest']);
            $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identityRegions = $this->identityRegionsFor($derived['manifest']);

            $this->assertConfiguration($manifest, $booklet);
            $this->persistProjections($template, $booklet, $manifest, $manifestHash, $actor);

            $template->forceFill([
                'identity_regions_json' => $identityRegions,
                'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                'native_bundle_version' => (string) ($bundle->manifest['bundle_version'] ?? 'nexiam.omr.native-bundle.v1'),
                'native_bundle_json' => [
                    'template' => $bundle->template,
                    'config' => $bundle->config,
                    'manifest' => $bundle->manifest,
                    'files' => $bundle->fileHashes,
                ],
                'native_bundle_hash' => $bundle->bundleHash,
                'engine_name' => (string) $bundle->manifest['engine']['name'],
                'engine_commit' => (string) $bundle->manifest['engine']['commit'],
                'patch_identity' => (string) $bundle->manifest['engine']['patch_set'],
                'review_manifest_json' => $manifest,
                'review_manifest_hash' => $manifestHash,
                'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'activated_by_user_id' => $actor->id,
                'activated_at' => now(),
            ])->save();

            $booklet->forceFill([
                'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
                'activated_by_user_id' => $actor->id,
                'activated_at' => now(),
            ])->save();

            $pair = OmrTemplateBookletPair::query()->create([
                'template_version_id' => $template->id,
                'booklet_version_id' => $booklet->id,
                'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->recordAuditEvent->handle('grading.omr_configuration_activated', [
                'template_version_id' => $template->id,
                'booklet_version_id' => $booklet->id,
                'native_bundle_hash' => $bundle->bundleHash,
                'review_manifest_hash' => $manifestHash,
                'field_count' => count($manifest['fields']),
            ], (string) $actor->id);

            return $pair;
        });
    }
    /**
     * Computes page-keyed identity regions (applicant number + optional
     * birthdate) from the derived manifest fields — the activation is the
     * single authority for identity geometry.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<int, array<string, array{x: float, y: float, w: float, h: float}>>
     */
    private function identityRegionsFor(array $manifest): array
    {
        $regions = [];

        foreach ($manifest['fields'] as $field) {
            $label = (string) $field['label'];
            $page = (int) $field['page'];
            $region = $field['region'];

            if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                $code = OmrIdentityField::CODE_APPLICANT_NUMBER;
            } elseif (str_starts_with($label, 'BIRTHDATE_')) {
                $code = OmrIdentityField::CODE_BIRTHDATE;
            } else {
                continue;
            }

            $existing = $regions[$page][$code] ?? null;
            $regions[$page][$code] = $existing === null ? $region : $this->union([$existing, $region]);
        }

        ksort($regions);

        return $regions;
    }

    /** @param array<string, mixed> $manifest */
    private function withProjections(array $manifest): array
    {
        $questions = [];
        $areas = [];

        foreach ($manifest['fields'] as $field) {
            if (str_starts_with((string) $field['label'], 'APPLICANT_NUMBER_')
                || str_starts_with((string) $field['label'], 'BIRTHDATE_')) {
                continue;
            }

            if (preg_match('/^([A-Z0-9_]+)_Q_(\d{1,3})$/', (string) $field['label'], $matches) !== 1) {
                throw new RuntimeException(sprintf('Native manifest contains an undeclared field label: %s.', $field['label']));
            }

            $areaCode = $matches[1];
            $questionNo = (int) $matches[2];
            $questions[$areaCode][$questionNo]['options'][] = $field;
        }

        foreach ($questions as $areaCode => $areaQuestions) {
            $projectedQuestions = [];

            foreach ($areaQuestions as $questionNo => $question) {
                $regions = array_map(static fn (array $field): array => $field['region'], $question['options']);
                $projectedQuestions[$questionNo] = [
                    'area_code' => $areaCode,
                    'question_no' => $questionNo,
                    'page' => (int) $question['options'][0]['page'],
                    'region' => $this->union($regions),
                    'options' => array_values(array_map(
                        static fn (array $field): array => [
                            'code' => $field['option'],
                            'display_order' => array_search($field['option'], ['A', 'B', 'C', 'D'], true) + 1,
                            'region' => $field['region'],
                            'native_label' => $field['label'],
                        ],
                        $question['options'],
                    )),
                ];
            }

            ksort($projectedQuestions);
            $questions[$areaCode] = $projectedQuestions;
            $areas[$areaCode] = [
                'code' => $areaCode,
                'max_items' => count($projectedQuestions),
                'display_order' => count($areas) + 1,
                'region' => $this->union(array_map(
                    static fn (array $question): array => $question['region'],
                    $projectedQuestions,
                )),
            ];
        }

        ksort($areas);
        $displayOrder = 1;
        foreach ($areas as &$area) {
            $area['display_order'] = $displayOrder++;
            ksort($questions[$area['code']]);
        }
        unset($area);

        $manifest['areas'] = array_values($areas);
        $manifest['questions'] = array_values(array_merge(...array_values($questions)));

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    private function assertConfiguration(array $manifest, OmrQuestionBookletVersion $booklet): void
    {
        $areas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('code');
        $bookletQuestions = [];

        foreach ($manifest['areas'] as $area) {
            $catalogArea = $areas->get($area['code']);
            if ($catalogArea === null || (int) $catalogArea->max_items !== (int) $area['max_items']) {
                throw new RuntimeException(sprintf('Native manifest area %s does not match the active aptitude catalog.', $area['code']));
            }
        }

        foreach ($manifest['questions'] as $question) {
            if ((int) $question['question_no'] !== count($bookletQuestions[$question['area_code']] ?? []) + 1) {
                throw new RuntimeException('Native manifest question positions must be contiguous per area.');
            }
            if (count($question['options']) !== 4 || array_map(static fn (array $option): string => $option['code'], $question['options']) !== ['A', 'B', 'C', 'D']) {
                throw new RuntimeException('Every native manifest question must contain options A through D once.');
            }
            $bookletQuestions[$question['area_code']][] = $question;
        }

        if (array_sum(array_map('count', $bookletQuestions)) === 0) {
            throw new RuntimeException('Native manifest contains no scorable questions.');
        }

        $authored = $booklet->questions()->orderBy('question_position')->get();

        if ($authored->isNotEmpty()) {
            $this->assertAuthoredQuestions($manifest, $authored);
        }
    }

    /**
     * Staff-authored booklets must already carry complete, contiguous keys;
     * activation validates them instead of generating keys.
     *
     * @param  \Illuminate\Support\Collection<int, OmrQuestionBookletQuestion>  $authored
     */
    private function assertAuthoredQuestions(array $manifest, mixed $authored): void
    {
        $byArea = $authored->groupBy('aptitude_area_id');

        foreach ($manifest['areas'] as $areaData) {
            $areaId = AptitudeArea::query()->where('code', $areaData['code'])->value('id');
            $rows = $byArea->get($areaId, collect())->values();

            if ($rows->count() !== (int) $areaData['max_items']) {
                throw new RuntimeException(sprintf(
                    'Authored booklet has %d questions for %s; the template declares %d.',
                    $rows->count(), $areaData['code'], $areaData['max_items'],
                ));
            }

            foreach ($rows as $index => $row) {
                if (! in_array((string) $row->correct_option_code, ['A', 'B', 'C', 'D'], true)) {
                    throw new RuntimeException('Authored correct options must be A, B, C, or D.');
                }
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function persistProjections(
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        array $manifest,
        string $manifestHash,
        User $actor,
    ): void {
        $areas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('code');
        $position = 1;

        foreach ((array) ($manifest['pages'] ?? []) as $pageData) {
            OmrAnswerSheetPage::query()->create([
                'template_version_id' => $template->id,
                'page_no' => (int) $pageData['page_no'],
                'width' => (int) $pageData['page_dimensions']['width'],
                'height' => (int) $pageData['page_dimensions']['height'],
                'page_manifest_json' => $manifest,
                'page_manifest_hash' => $manifestHash,
            ]);
        }

        if (! isset($manifest['pages']) && $template->pages()->doesntExist()) {
            OmrAnswerSheetPage::query()->create([
                'template_version_id' => $template->id,
                'page_no' => 1,
                'width' => (int) $template->page_width,
                'height' => (int) $template->page_height,
                'page_manifest_json' => $manifest,
                'page_manifest_hash' => $manifestHash,
            ]);
        }

        foreach ($manifest['areas'] as $areaData) {
            $area = OmrTemplateArea::query()->create([
                'template_version_id' => $template->id,
                'aptitude_area_id' => $areas[$areaData['code']]->id,
                'display_order' => $areaData['display_order'],
                'max_items' => $areaData['max_items'],
                ...$this->regionColumns($areaData['region']),
                'projection_manifest_hash' => $manifestHash,
                'projection_source' => 'activation_manifest',
            ]);

            foreach (array_values(array_filter($manifest['questions'], static fn (array $question): bool => $question['area_code'] === $areaData['code'])) as $questionData) {
                $question = OmrTemplateQuestion::query()->create([
                    'template_area_id' => $area->id,
                    'page_no' => (int) ($questionData['page'] ?? 1),
                    'question_no' => $questionData['question_no'],
                    ...$this->regionColumns($questionData['region']),
                    'projection_manifest_hash' => $manifestHash,
                    'projection_source' => 'activation_manifest',
                ]);

                foreach ($questionData['options'] as $optionData) {
                    OmrTemplateQuestionOption::query()->create([
                        'template_question_id' => $question->id,
                        'code' => $optionData['code'],
                        'display_order' => $optionData['display_order'],
                        ...$this->regionColumns($optionData['region']),
                        'projection_manifest_hash' => $manifestHash,
                        'projection_source' => 'activation_manifest',
                    ]);
                }

                $this->persistBookletQuestion($booklet, $areas, $areaData, $question, $position);
                $position++;
            }
        }
    }

    /** @param \Illuminate\Support\Collection<int, AptitudeArea> $areas */
    private function persistBookletQuestion(
        OmrQuestionBookletVersion $booklet,
        mixed $areas,
        array $areaData,
        OmrTemplateQuestion $question,
        int $position,
    ): void {
        $authored = $booklet->questions()
            ->where('aptitude_area_id', $areas[$areaData['code']]->id)
            ->orderBy('question_position')
            ->skip(max(0, (int) $question->question_no - 1))
            ->first();

        if ($authored !== null) {
            $authored->update(['template_question_id' => $question->id]);

            return;
        }

        $booklet->questions()->create([
            'template_question_id' => $question->id,
            'aptitude_area_id' => $areas[$areaData['code']]->id,
            'question_position' => $position,
            'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
        ]);
    }

    /** @param array{x: float, y: float, w: float, h: float} $region */
    private function regionColumns(array $region): array
    {
        return [
            'region_x' => $region['x'],
            'region_y' => $region['y'],
            'region_w' => $region['w'],
            'region_h' => $region['h'],
        ];
    }

    /** @param list<array{x: float, y: float, w: float, h: float}> $regions */
    private function union(array $regions): array
    {
        return [
            'x' => min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'y' => min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
            'w' => max(array_map(static fn (array $region): float => (float) $region['x'] + $region['w'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['x'], $regions)),
            'h' => max(array_map(static fn (array $region): float => (float) $region['y'] + $region['h'], $regions)) - min(array_map(static fn (array $region): float => (float) $region['y'], $regions)),
        ];
    }
}
