<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\GeometryProjectionResult;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_column;
use function count;
use function hash;
use function in_array;
use function json_encode;
use function ksort;
use function sprintf;
use function str_starts_with;

/**
 * Activates one native OMR configuration from the pinned bundle. The
 * version-owned page/area/question/option projection is owned by
 * `OmrTemplateGeometryProjector`; this action validates the pair contract
 * against the booklet with typed failures, links or key-generates booklet
 * questions, and atomically swaps the active versions.
 */
final class ActivateOmrConfiguration
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly OmrTemplateGeometryProjector $geometryProjector,
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
            $projection = $this->geometryProjector->project($template, $derived['manifest']);
            $manifest = [
                ...$derived['manifest'],
                'areas' => $projection->areas,
                'questions' => $projection->questions,
            ];
            $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identityRegions = $this->identityRegionsFor($derived['manifest']);

            $this->assertPairContract($projection, $booklet);
            $this->linkBookletQuestions($projection, $booklet);

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
                'projection_hash' => $projection->projectionHash,
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
            $regions[$page][$code] = $existing === null ? $region : OmrTemplateGeometryProjector::unionRegions([$existing, $region]);
        }

        ksort($regions);

        return $regions;
    }

    /**
     * Pair-contract validation with typed failures. The template projection
     * must match the active aptitude catalog, every manifest area must be
     * fully covered by authored booklet questions, and every authored correct
     * option must belong to the question's declared option set.
     */
    private function assertPairContract(GeometryProjectionResult $projection, OmrQuestionBookletVersion $booklet): void
    {
        $areas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('code');

        foreach ($projection->areas as $area) {
            $catalogArea = $areas->get($area['code']);
            if ($catalogArea === null || (int) $catalogArea->max_items !== (int) $area['max_items']) {
                throw new RuntimeException(sprintf('Native manifest area %s does not match the active aptitude catalog.', $area['code']));
            }
        }

        if (count($projection->questions) === 0) {
            throw new RuntimeException('Native manifest contains no scorable questions.');
        }

        $authored = $booklet->questions()->orderBy('question_position')->get();
        if ($authored->isEmpty()) {
            return; // An empty booklet is key-generated below.
        }

        $byArea = $authored->groupBy('aptitude_area_id');

        foreach ($projection->areas as $areaData) {
            $areaId = $areas[$areaData['code']]->id;
            $rows = $byArea->get($areaId, collect())->values();

            if ($rows->count() !== (int) $areaData['max_items']) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    sprintf(
                        'Authored booklet has %d questions for %s; the template declares %d.',
                        $rows->count(),
                        $areaData['code'],
                        $areaData['max_items'],
                    ),
                );
            }
        }

        foreach ($projection->projectedQuestions as $question) {
            $areaId = $areas[$question['area_code']]->id;
            $row = $authored
                ->where('aptitude_area_id', $areaId)
                ->sortBy('question_position')
                ->values()
                ->get($question['question_no'] - 1);

            if ($row === null) {
                throw new OmrTemplateProjectionException(
                    'pair_geometry_coverage_mismatch',
                    sprintf(
                        'Authored booklet does not cover %s_Q_%d.',
                        $question['area_code'],
                        $question['question_no'],
                    ),
                );
            }

            $declared = array_column($question['options'], 'code');
            if (! in_array((string) $row->correct_option_code, $declared, true)) {
                throw new OmrTemplateProjectionException(
                    'pair_option_set_mismatch',
                    sprintf(
                        'Authored booklet question %d has correct option %s outside the template declared option set for %s_Q_%d.',
                        (int) $row->question_position,
                        (string) $row->correct_option_code,
                        $question['area_code'],
                        $question['question_no'],
                    ),
                );
            }
        }
    }

    /**
     * Links each projected template question to its authored booklet row
     * (matched by area + within-area position) or key-generates a booklet
     * row cycling the question's declared option set.
     */
    private function linkBookletQuestions(GeometryProjectionResult $projection, OmrQuestionBookletVersion $booklet): void
    {
        $areaIdsByCode = AptitudeArea::query()->where('is_active', true)->pluck('id', 'code');
        $position = 1;

        foreach ($projection->projectedQuestions as $question) {
            $areaId = (int) $areaIdsByCode[$question['area_code']];
            $authored = $booklet->questions()
                ->where('aptitude_area_id', $areaId)
                ->orderBy('question_position')
                ->skip($question['question_no'] - 1)
                ->first();

            if ($authored !== null) {
                $authored->update(['template_question_id' => $question['template_question_id']]);

                $position++;

                continue;
            }

            $declared = array_column($question['options'], 'code');
            $booklet->questions()->create([
                'template_question_id' => $question['template_question_id'],
                'aptitude_area_id' => $areaId,
                'question_position' => $position,
                'correct_option_code' => $declared[($position - 1) % count($declared)],
            ]);

            $position++;
        }
    }
}
