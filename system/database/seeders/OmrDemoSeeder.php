<?php

namespace Database\Seeders;

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Assessment\Actions\Omr\RetireOmrConfiguration;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Models\Foundation\AptitudeArea;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function hash;
use function json_encode;
use function max;
use function min;
use function str_starts_with;

class OmrDemoSeeder extends Seeder
{
    private const BUNDLE_VERSIONS = [
        1 => ['booklet_version' => 1, 'key_label' => 'v1'],
        2 => ['booklet_version' => 2, 'key_label' => 'v2'],
        3 => ['booklet_version' => 3, 'key_label' => 'v3 two-page'],
        4 => ['booklet_version' => 4, 'key_label' => 'v4 long-bond'],
    ];

    public function run(): void
    {
        foreach (self::BUNDLE_VERSIONS as $bundleVersion => $plan) {
            $this->seedPair($bundleVersion, (int) $plan['booklet_version'], (string) $plan['key_label']);
        }

        $this->seedAuthoredV5WithBirthdate();
        $this->seedCompactProfile();
        $this->seedDraftBookletB();
        $this->retireFirstPair();
    }

    private function seedPair(int $bundleVersion, int $bookletVersion, string $keyLabel): void
    {
        $alreadySeeded = OmrTemplateBookletPair::query()
            ->whereHas('templateVersion', fn ($query) => $query
                ->where('version', $bundleVersion)
                ->whereHas('template', fn ($template) => $template->where('code', 'SYN-OMR-ONE-PAGE')))
            ->exists()
            || OmrAnswerSheetTemplateVersion::query()
                ->where('version', $bundleVersion)
                ->whereHas('template', fn ($template) => $template->where('code', 'SYN-OMR-ONE-PAGE'))
                ->exists();

        if ($alreadySeeded) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', $bundleVersion);
        $derived = app(NativeManifestDeriver::class)->derive($bundle);
        $identityFields = array_values(array_filter(
            $derived['manifest']['fields'],
            static fn (array $field): bool => str_starts_with($field['label'], 'APPLICANT_NUMBER_'),
        ));
        $identityRegion = $this->identityRegionsPayload($identityFields);

        [$templateVersionId, $seededBookletVersionId] = DB::transaction(function () use ($bundle, $actor, $identityRegion, $bundleVersion, $bookletVersion, $keyLabel): array {
            $template = OmrAnswerSheetTemplate::query()->updateOrCreate(
                ['code' => 'SYN-OMR-ONE-PAGE'],
                ['name' => 'Synthetic native one-page aptitude sheet', 'is_synthetic' => true],
            );
            $booklet = OmrQuestionBooklet::query()->updateOrCreate(
                ['code' => 'SYN-OMR-BOOKLET-A'],
                ['name' => 'Synthetic native aptitude booklet A'],
            );

            $templateVersion = OmrAnswerSheetTemplateVersion::query()->updateOrCreate(
                ['template_id' => $template->id, 'version' => $bundleVersion],
                [
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => (int) $bundle->pageDimensions(1)[0],
                    'page_height' => (int) $bundle->pageDimensions(1)[1],
                    'identity_regions_json' => $identityRegion,
                    'provider_config_json' => [
                        'driver' => 'fake',
                        'fixture' => true,
                        'native_bundle' => "SYN-OMR-ONE-PAGE:v{$bundleVersion}",
                    ],
                    'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                ],
            );
            $seededBookletVersion = OmrQuestionBookletVersion::query()->updateOrCreate(
                ['booklet_id' => $booklet->id, 'version' => $bookletVersion],
                [
                    'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                    'answer_key_hash' => hash('sha256', "SYN-OMR-BOOKLET-A:v{$bookletVersion}:answer-key"),
                ],
            );

            if ($templateVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_DRAFT
                || $seededBookletVersion->status !== OmrQuestionBookletVersion::STATUS_DRAFT) {
                throw new RuntimeException('Synthetic OMR seed cannot rewrite an activated configuration.');
            }

            $seededBookletVersion->questions()->delete();
            $templateVersion->areas()->delete();

            return [(int) $templateVersion->id, (int) $seededBookletVersion->id];
        });

        app(ActivateOmrConfiguration::class)->handle(
            OmrAnswerSheetTemplateVersion::query()->findOrFail($templateVersionId),
            OmrQuestionBookletVersion::query()->findOrFail($seededBookletVersionId),
            $actor,
        );

        $this->command?->info("Synthetic OMR native bundle pair ready: SYN-OMR-ONE-PAGE v{$bundleVersion} + SYN-OMR-BOOKLET-A v{$bookletVersion} ({$keyLabel}).");
    }

    /**
    /**
     * Seeds the v5 long-bond 2-page bundle authored through the builder path
     * (identity + birthdate grids on every page, VERBAL/QUANT 4-column bands
     * kept inside the 80px printable margin) with an overlay, and activates it
     * with a matching booklet.
     */
    private function seedAuthoredV5WithBirthdate(): void
    {
        $alreadySeeded = OmrTemplateBookletPair::query()
            ->whereHas('templateVersion', fn ($query) => $query
                ->where('version', 5)
                ->whereHas('template', fn ($template) => $template->where('code', 'SYN-OMR-ONE-PAGE')))
            ->exists()
            || OmrAnswerSheetTemplateVersion::query()
                ->where('version', 5)
                ->whereHas('template', fn ($template) => $template->where('code', 'SYN-OMR-ONE-PAGE'))
                ->exists();

        if ($alreadySeeded) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $code = 'SYN-OMR-ONE-PAGE';

        $templateJson = $this->v5TemplateJson();
        $overlay = [
            'title' => 'NEXIAM ANSWER SHEET',
            'header_lines' => ['EX-DEMO-1 · MAIN HALL 101'],
            'footer_lines' => ['Shade completely. Erasures void the sheet.'],
            'name_box_label' => 'NAME (PRINT):',
            'per_page' => [
                ['page_no' => 1, 'note' => 'Answer only in this booklet.'],
            ],
        ];

        $bundleDir = base_path('app/Assessment/Omr/NativeBundles/'.$code.'/v5');
        $directory = dirname(__DIR__, 2).'/app/Assessment/Omr/NativeBundles/'.$code.'/v5';
        $durableExists = \Illuminate\Support\Facades\Storage::disk('local')->exists('omr-bundles/'.$code.'/v5');
        if (! $durableExists && ! is_dir($bundleDir)) {
            // Builder-author path: writes the canonical bundle and creates the draft row.
            $draft = app(SaveAuthoredTemplateDraft::class)->handle(
                $code,
                'Synthetic long-bond answer sheet (birthdate)',
                $templateJson,
                $actor,
                $overlay,
                true,
            );
        } else {
            $bundle = NativeBundle::load($code, 5);
            $derived = app(NativeManifestDeriver::class)->derive($bundle);

            $template = OmrAnswerSheetTemplate::query()->updateOrCreate(
                ['code' => $code],
                ['name' => 'Synthetic long-bond answer sheet (birthdate)', 'is_synthetic' => true],
            );
            $draft = OmrAnswerSheetTemplateVersion::query()->updateOrCreate(
                ['template_id' => $template->id, 'version' => 5],
                [
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => (int) $bundle->pageDimensions(1)[0],
                    'page_height' => (int) $bundle->pageDimensions(1)[1],
                    'identity_regions_json' => [],
                    'provider_config_json' => ['driver' => 'fake', 'fixture' => true],
                    'config_hash' => hash('sha256', json_encode($bundle->config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'template_json_hash' => hash('sha256', json_encode($bundle->template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
                    'native_bundle_json' => [
                        'template' => $bundle->template,
                        'config' => $bundle->config,
                        'manifest' => $bundle->manifest,
                        'files' => $bundle->fileHashes,
                    ],
                    'native_bundle_hash' => $bundle->bundleHash,
                    'review_manifest_json' => $derived['manifest'],
                    'review_manifest_hash' => $derived['hash'],
                    'engine_name' => (string) $bundle->manifest['engine']['name'],
                    'engine_commit' => (string) $bundle->manifest['engine']['commit'],
                    'patch_identity' => (string) $bundle->manifest['engine']['patch_set'],
                    'print_overlay_json' => $overlay,
                ],
            );
        }

        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();
        $verbal = AptitudeArea::query()->where('code', 'VERBAL')->firstOrFail();
        $bookletCode = 'SYN-OMR-BOOKLET-C';
        OmrQuestionBooklet::query()->updateOrCreate(
            ['code' => $bookletCode],
            ['name' => 'Synthetic aptitude booklet C (two-page long bond)'],
        );

        $questions = [];
        foreach ([[$verbal, 'VERBAL'], [$quant, 'QUANT']] as [$area, $areaCode]) {
            for ($i = 1; $i <= (int) $area->max_items; $i++) {
                $questions[] = [
                    'area_id' => (int) $area->id,
                    'position' => $areaCode === 'VERBAL' ? $i : 100 + $i,
                    'correct_option_code' => ['A', 'B', 'C', 'D'][($i - 1) % 4],
                ];
            }
        }
        $bookletVersion = app(SaveAuthoredBookletVersion::class)->handle(
            $bookletCode,
            'Synthetic aptitude booklet C (two-page long bond)',
            $questions,
            $actor,
        );

        app(ActivateOmrConfiguration::class)->handle($draft->fresh(), $bookletVersion, $actor);

        $this->command?->info('Synthetic OMR v5 long-bond pair ready: SYN-OMR-ONE-PAGE v5 (birthdate) + SYN-OMR-BOOKLET-C v'.$bookletVersion->version.'.');
    }

    /**
     * Seeds the canonical one-page six-domain profile through the server-owned
     * layout solver, then pairs it with a matching authored answer key.
     */
    private function seedCompactProfile(): void
    {
        $templateCode = 'SYN-OMR-PROFILE-150';
        $bookletCode = 'SYN-OMR-PROFILE-KEY';
        $areaCodes = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
        $expectedSections = [];
        foreach ($areaCodes as $index => $areaCode) {
            $expectedSections[] = [
                'area_code' => $areaCode,
                'question_from' => 1,
                'question_to' => 25,
                'option_count' => 4,
                'break_before' => $index === 0 ? null : 'next_column',
            ];
        }

        $alreadyPaired = OmrTemplateBookletPair::query()
            ->whereHas('templateVersion', fn ($query) => $query->whereHas('template', fn ($template) => $template->where('code', $templateCode)))
            ->whereHas('bookletVersion', fn ($query) => $query->whereHas('booklet', fn ($booklet) => $booklet->where('code', $bookletCode)))
            ->exists();

        if ($alreadyPaired) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $areas = AptitudeArea::query()
            ->whereIn('code', $areaCodes)
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        if ($areas->count() !== count($areaCodes)) {
            throw new RuntimeException('Synthetic OMR compact profile requires all six active aptitude areas.');
        }

        $template = OmrAnswerSheetTemplate::query()->where('code', $templateCode)->first();
        $draft = $template === null
            ? null
            : OmrAnswerSheetTemplateVersion::query()
                ->where('template_id', $template->id)
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
                ->latest('version')
                ->first();

        if ($draft !== null) {
            $savedBlueprint = is_array($draft->layout_blueprint_json) ? $draft->layout_blueprint_json : [];
            $blueprintMatches = ($savedBlueprint['schema'] ?? null) === 'nexiam.omr.layout-blueprint.v1'
                && ($savedBlueprint['paper'] ?? null) === 'long'
                && ($savedBlueprint['columns'] ?? null) === 6
                && ($savedBlueprint['identity'] ?? null) === [
                    'applicant_number' => true,
                    'birthdate' => false,
                    'repeat_on_every_page' => true,
                ]
                && ($savedBlueprint['sections'] ?? null) === $expectedSections;
            if (! $blueprintMatches) {
                Storage::disk('local')->deleteDirectory('omr-bundles/'.$templateCode.'/v'.$draft->version);
                $draft->delete();
                $draft = null;
            }
        }

        if ($draft === null) {
            $sections = [];
            foreach ($areaCodes as $index => $areaCode) {
                $sections[] = [
                    'area_code' => $areaCode,
                    'question_from' => 1,
                    'question_to' => 25,
                    'option_count' => 4,
                    'break_before' => $index === 0 ? null : 'next_column',
                ];
            }

            $draft = app(SaveAuthoredTemplateDraft::class)->handle(
                $templateCode,
                'Synthetic compact aptitude profile (150 items)',
                [],
                $actor,
                [
                    'title' => 'NEXIAM ANSWER SHEET',
                    'header_lines' => ['SIX-DOMAIN PRACTICE PROFILE'],
                    'edq_header' => true,
                    'edq_header_label' => 'EDQ / HEADER ZONE',
                    'exam_information_lines' => ['EXAM: __________ · BATCH: __________'],
                    'instructions_lines' => ['Shade one answer per question.'],
                    'birthdate_label' => 'BIRTHDATE:',
                    'birthdate_placeholder' => 'MMDDYYYY',
                    'footer_lines' => [],
                    'show_calibration' => false,
                    'show_frame' => false,
                    'edq_layout' => ['columns' => 3, 'gap_mm' => 4, 'reserve_native_identity' => true],
                    'name_box_label' => 'NAME (PRINT):',
                    'per_page' => [],
                ],
                true,
                [
                    'schema' => 'nexiam.omr.layout-blueprint.v1',
                    'paper' => 'long',
                    'columns' => 6,
                    'identity' => [
                        'applicant_number' => true,
                        'birthdate' => false,
                        'repeat_on_every_page' => true,
                    ],
                    'sections' => $sections,
                ],
            );
        }

        $booklet = OmrQuestionBooklet::query()->firstOrCreate(
            ['code' => $bookletCode],
            ['name' => 'Synthetic compact aptitude profile answer key'],
        );
        $questions = [];
        $position = 1;
        foreach ($areaCodes as $areaCode) {
            for ($questionNo = 1; $questionNo <= 25; $questionNo++) {
                $questions[] = [
                    'area_id' => (int) $areas[$areaCode]->id,
                    'question_no' => $questionNo,
                    'position' => $position,
                    'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
                ];
                $position++;
            }
        }

        $bookletDraft = OmrQuestionBookletVersion::query()
            ->where('booklet_id', $booklet->id)
            ->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)
            ->latest('version')
            ->first();
        $bookletVersion = $bookletDraft;
        $bookletMatches = $bookletDraft !== null && $bookletDraft->questions()->count() === count($questions);
        if ($bookletMatches) {
            foreach ($bookletDraft->questions()->orderBy('question_position')->get(['aptitude_area_id', 'question_position', 'correct_option_code']) as $index => $row) {
                $expected = $questions[$index];
                if ((int) $row->aptitude_area_id !== (int) $expected['area_id']
                    || (int) $row->question_position !== (int) $expected['position']
                    || (string) $row->correct_option_code !== (string) $expected['correct_option_code']) {
                    $bookletMatches = false;
                    break;
                }
            }
        }
        if (! $bookletMatches) {
            $bookletVersion = app(SaveAuthoredBookletVersion::class)->handle(
                $bookletCode,
                'Synthetic compact aptitude profile answer key',
                $questions,
                $actor,
            );
        }

        app(ActivateOmrConfiguration::class)->handle($draft->fresh(), $bookletVersion->fresh(), $actor);
        $this->command?->info('Synthetic compact OMR profile ready: '.$templateCode.' + '.$bookletCode.' (150 items, one page).');
    }
    /**
     * Seeds an unpaired QUANT-only draft booklet (all-A key) so the
     * "unpaired drafts" banner and draft editing have live data.
     */
    private function seedDraftBookletB(): void
    {
        $code = 'SYN-OMR-BOOKLET-B';
        $already = OmrQuestionBooklet::query()->where('code', $code)->exists();
        if ($already) {
            return;
        }

        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $quant = AptitudeArea::query()->where('code', 'QUANT')->firstOrFail();

        $questions = [];
        for ($i = 1; $i <= 100; $i++) {
            $questions[] = ['area_id' => (int) $quant->id, 'position' => $i, 'correct_option_code' => 'A'];
        }

        $version = app(SaveAuthoredBookletVersion::class)->handle($code, 'Synthetic aptitude booklet B (draft)', $questions, $actor);
        $this->command?->info('Synthetic OMR draft booklet ready: SYN-OMR-BOOKLET-B v'.$version->version.' (unpaired).');
    }

    /**
     * Retires the legacy v1 pair so retirement exclusion has live data while
     * the remaining active pairs keep capture working. Idempotent: once the
     * v1 pair is retired (or absent) this is a no-op.
     */
    private function retireFirstPair(): void
    {
        $pair = OmrTemplateBookletPair::query()
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->whereHas('templateVersion', fn ($query) => $query
                ->where('version', 1)
                ->whereHas('template', fn ($template) => $template->where('code', 'SYN-OMR-ONE-PAGE')))
            ->first();

        if ($pair === null) {
            return;
        }

        app(RetireOmrConfiguration::class)->handle('pair', (int) $pair->id, User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail());
        $this->command?->info('Synthetic OMR pair #'.$pair->id.' retired (legacy v1).');
    }

    /**
     * Two-page long-bond geometry, all blocks inside the 80px printable margin.
     *
     * @return array<string, mixed>
     */
    private function v5TemplateJson(): array
    {
        $identity = [
            'ApplicantNumber' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                'bubblesGap' => 60,
                'labelsGap' => 66,
                'origin' => [200, 260],
            ],
            'Birthdate' => [
                'fieldType' => 'QTYPE_INT',
                'fieldLabels' => ['BIRTHDATE_1..6'],
                'bubblesGap' => 60,
                'labelsGap' => 66,
                'origin' => [1000, 260],
            ],
        ];

        $blocksFor = function (string $prefix): array {
            $blocks = [];
            foreach (['A', 'B', 'C', 'D'] as $index => $part) {
                $blocks['Part'.$part] = [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $prefix, $index * 25 + 1, ($index + 1) * 25)],
                    'bubblesGap' => 66,
                    'labelsGap' => 80,
                    'origin' => [180 + $index * 440, 900],
                ];
            }

            return $blocks;
        };

        return [
            'bubbleDimensions' => [56, 56],
            'pageDimensions' => [2060, 3144],
            'pages' => [
                [
                    'page_no' => 1,
                    'pageDimensions' => [2060, 3144],
                    'fieldBlocks' => [...$identity, ...$blocksFor('VERBAL')],
                    'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
                ],
                [
                    'page_no' => 2,
                    'pageDimensions' => [2060, 3144],
                    'fieldBlocks' => [...$identity, ...$blocksFor('QUANT')],
                    'preProcessors' => [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $identityFields
     * @return array<string, mixed>
     */
    private function identityRegionsPayload(array $identityFields): array
    {
        $byPage = [];
        foreach ($identityFields as $field) {
            $byPage[(int) ($field['page'] ?? 1)][] = $field['region'];
        }

        if (count($byPage) === 1) {
            return ['applicant_number' => $this->union($byPage[1] ?? [])];
        }

        $payload = [];
        foreach ($byPage as $pageNo => $regions) {
            $payload[(string) $pageNo] = ['applicant_number' => $this->union($regions)];
        }

        return $payload;
    }

    /** @param list<array{x: float, y: float, w: float, h: float}> $regions */
    private function union(array $regions): array
    {
        if ($regions === []) {
            throw new RuntimeException('Synthetic OMR native bundle has no applicant-number bubbles.');
        }

        $minX = min(array_map(static fn (array $region): float => $region['x'], $regions));
        $minY = min(array_map(static fn (array $region): float => $region['y'], $regions));
        $maxX = max(array_map(static fn (array $region): float => $region['x'] + $region['w'], $regions));
        $maxY = max(array_map(static fn (array $region): float => $region['y'] + $region['h'], $regions));

        return ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX, 'h' => $maxY - $minY];
    }
}
