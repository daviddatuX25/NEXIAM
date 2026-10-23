<?php

namespace Database\Seeders;

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\OmrAnswerSheetPage;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletItem;
use App\Models\Assessment\OmrBookletSection;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrBookletSetItem;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateArea;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateQuestion;
use App\Models\Assessment\OmrTemplateQuestionOption;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IcatRealMaterialsSeeder extends Seeder
{
    private const SECTIONS = [
        [
            'code' => 'VOCABULARY',
            'name' => 'Vocabulary',
            'page_no' => 1,
            'count' => 40,
            'area_code' => 'PROFILE_VERBAL',
            'tokens' => ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'],
        ],
        [
            'code' => 'COMPUTATION',
            'name' => 'Computation',
            'page_no' => 2,
            'count' => 30,
            'area_code' => 'PROFILE_QUANT',
            'tokens' => ['A', 'B', 'C', 'D', 'E'],
        ],
        [
            'code' => 'SPATIAL',
            'name' => 'Spatial Relations',
            'page_no' => 2,
            'count' => 20,
            'area_code' => 'SPATIAL',
            'tokens' => ['A', 'B', 'C', 'D'],
        ],
        [
            'code' => 'WORD_COMP',
            'name' => 'Word Comparison',
            'page_no' => 2,
            'count' => 100,
            'area_code' => 'LOGIC',
            'tokens' => ['S', 'D'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $institution = Institution::query()->firstOrFail();
            $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first() ?? User::query()->firstOrFail();

            // 1. Archive synthetic booklets so they do not clutter active operational lists
            OmrQuestionBookletVersion::query()
                ->whereHas('booklet', fn ($q) => $q->where('code', 'like', 'SYN-%'))
                ->whereNull('archived_at')
                ->update(['archived_at' => now()]);

            if (! TestInstrument::query()->where('code', 'OASIS-3-ICAT')->exists()
                || ! TestKit::query()->where('code', 'TK-ICAT-2026')->exists()) {
                (new FoundationalScoringSeeder)->run();
                if (! app()->environment('testing')) {
                    $attemptIds = AdmissionAttempt::query()
                        ->where('reference_code', 'like', 'APP-ICAT-2026-%')
                        ->pluck('applicant_identity_id');
                    AdmissionAttempt::query()
                        ->where('reference_code', 'like', 'APP-ICAT-2026-%')
                        ->delete();
                    ApplicantIdentity::query()
                        ->whereIn('id', $attemptIds)
                        ->delete();
                }
            }

            // 2. Ensure Test Instrument OASIS-3-ICAT (190 items) links to proper aptitude areas
            $instrument = TestInstrument::query()->where('code', 'OASIS-3-ICAT')->firstOrFail();
            $instrumentVersion = TestInstrumentVersion::query()->findOrFail((int) $instrument->current_approved_version_id);
            // 3. Compile real ICAT-AS-2026 Answer Sheet Version 1
            $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
                ['code' => 'ICAT-AS-2026'],
                [
                    'institution_id' => $institution->id,
                    'name' => 'ICAT Answersheet 2026',
                    'is_synthetic' => true,
                ],
            );

            OmrAnswerSheetTemplateVersion::query()
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                ->where('template_id', '!=', $template->id)
                ->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_RETIRED]);

            // Stable UUIDs for physical sections
            $physicalSections = [];
            $allPhysicalItems = [];
            $globalPos = 1;

            foreach (self::SECTIONS as $index => $secDef) {
                $sectionUid = sprintf('00000000-0000-4000-9000-%012d', $index + 1);
                $items = [];
                for ($num = 1; $num <= $secDef['count']; $num++) {
                    $itemUid = sprintf('00000000-0000-4000-8000-%012d', $globalPos);
                    $nativeLabel = sprintf('%s_Q_%d', $secDef['code'], $num);
                    $item = [
                        'physical_item_uid' => $itemUid,
                        'printed_section_uid' => $sectionUid,
                        'native_label' => $nativeLabel,
                        'page_no' => $secDef['page_no'],
                        'printed_number' => $num,
                        'global_position' => $globalPos,
                        'option_tokens' => $secDef['tokens'],
                        'guide_id' => sprintf('%s_GUIDE_%d', $secDef['code'], $num),
                    ];
                    $items[] = $item;
                    $allPhysicalItems[$globalPos] = $item;
                    $globalPos++;
                }

                $physicalSections[] = [
                    'printed_section_uid' => $sectionUid,
                    'native_code' => $secDef['code'],
                    'name' => $secDef['name'],
                    'page_no' => $secDef['page_no'],
                    'numbering' => ['start' => 1, 'step' => 1, 'prefix' => '', 'suffix' => ''],
                    'expected_item_count' => $secDef['count'],
                    'display_order' => $index + 1,
                    'items' => $items,
                ];
            }

            $physicalInventory = [
                'revision' => 1,
                'sections' => $physicalSections,
            ];
            $manifestFields = [];
            foreach ($allPhysicalItems as $item) {
                $gPos = $item['global_position'];
                $qNum = $item['printed_number'];
                $count = ($item['page_no'] === 1) ? 40 : ($gPos <= 70 ? 30 : ($gPos <= 90 ? 20 : 100));
                $normY = round(0.05 + (0.85 * ($qNum - 1) / $count), 6);
                $pixelY = round($normY * 3508, 1);
                foreach ($item['option_tokens'] as $optIdx => $token) {
                    $normX = round(0.1 + (0.04 * $optIdx), 6);
                    $pixelX = round($normX * 2480, 1);
                    $manifestFields[] = [
                        'page' => (int) $item['page_no'],
                        'label' => (string) $item['native_label'],
                        'option' => (string) $token,
                        'pixel' => [
                            'x' => $pixelX,
                            'y' => $pixelY,
                            'w' => 32.0,
                            'h' => 32.0,
                        ],
                        'region' => [
                            'x' => $normX,
                            'y' => $normY,
                            'w' => 0.03,
                            'h' => round(0.80 / $count, 6),
                        ],
                    ];
                }
            }

            $manifestPages = [
                ['page_no' => 1, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
                ['page_no' => 2, 'page_dimensions' => ['width' => 2480, 'height' => 3508]],
            ];

            $reviewManifest = [
                'template_code' => 'ICAT-AS-2026',
                'template_version' => 1,
                'fields' => $manifestFields,
                'pages' => $manifestPages,
            ];

            $pageManifest = [
                'template_code' => 'ICAT-AS-2026',
                'template_version' => 1,
                'fields' => $manifestFields,
                'pages' => $manifestPages,
            ];

            $projectionHash = hash('sha256', json_encode($pageManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $reviewHash = hash('sha256', json_encode($reviewManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $sheetVersion = OmrAnswerSheetTemplateVersion::query()->firstOrCreate(
                ['template_id' => $template->id, 'version' => 1],
                [
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'page_width' => 2480,
                    'page_height' => 3508,
                    'page_count' => 2,
                    'registration_mode' => 'document_bound',
                    'qr_mode' => 'qr_disabled',
                    'identity_regions_json' => [],
                    'provider_config_json' => [],
                    'config_hash' => str_repeat('a', 64),
                    'native_bundle_hash' => str_repeat('b', 64),
                    'native_bundle_json' => ['version' => 1, 'format' => 'canonical-v1'],
                    'native_bundle_version' => 'v1',
                    'engine_name' => 'omr-engine',
                    'engine_commit' => str_repeat('e', 40),
                    'patch_identity' => 'patch-icat-2026',
                    'review_manifest_hash' => $reviewHash,
                    'template_json_hash' => str_repeat('d', 64),
                    'authoring_manifest' => [
                        'source_kind' => 'uploaded_original',
                        'page_count' => 2,
                        'physical_inventory' => $physicalInventory,
                        'sections' => $physicalSections,
                    ],
                    'review_manifest_json' => $reviewManifest,
                ],
            );

            // Create OmrAnswerSheetPage rows
            OmrAnswerSheetPage::query()->firstOrCreate(
                ['template_version_id' => $sheetVersion->id, 'page_no' => 1],
                [
                    'width' => 2480,
                    'height' => 3508,
                    'page_manifest_json' => $pageManifest,
                    'page_manifest_hash' => $projectionHash,
                ],
            );
            OmrAnswerSheetPage::query()->firstOrCreate(
                ['template_version_id' => $sheetVersion->id, 'page_no' => 2],
                [
                    'width' => 2480,
                    'height' => 3508,
                    'page_manifest_json' => $pageManifest,
                    'page_manifest_hash' => $projectionHash,
                ],
            );

            // Populate Areas & Questions for Answer Sheet Version 1
            if ($sheetVersion->areas()->doesntExist()) {
                $areasByCode = AptitudeArea::query()->pluck('id', 'code');
                foreach (self::SECTIONS as $index => $secDef) {
                    $area = OmrTemplateArea::query()->create([
                        'template_version_id' => $sheetVersion->id,
                        'aptitude_area_id' => $areasByCode[$secDef['area_code']] ?? 1,
                        'display_order' => $index + 1,
                        'max_items' => $secDef['count'],
                        'region_x' => 0.05,
                        'region_y' => 0.05,
                        'region_w' => 0.9,
                        'region_h' => 0.9,
                        'projection_manifest_hash' => $projectionHash,
                    ]);

                    $startGlobal = ($index === 0) ? 1 : ($index === 1 ? 41 : ($index === 2 ? 71 : 91));
                    for ($i = 0; $i < $secDef['count']; $i++) {
                        $qNum = $i + 1;
                        $gPos = $startGlobal + $i;
                        $pItem = $allPhysicalItems[$gPos];

                        $question = OmrTemplateQuestion::query()->create([
                            'template_area_id' => $area->id,
                            'template_version_id' => $sheetVersion->id,
                            'page_no' => $secDef['page_no'],
                            'question_no' => $qNum,
                            'global_position' => $gPos,
                            'native_label' => $pItem['native_label'],
                            'physical_item_uid' => $pItem['physical_item_uid'],
                            'printed_section_uid' => $pItem['printed_section_uid'],
                            'region_x' => 0.1,
                            'region_y' => round(0.05 + (0.85 * ($qNum - 1) / $secDef['count']), 6),
                            'region_w' => 0.3,
                            'region_h' => round(0.80 / $secDef['count'], 6),
                            'projection_manifest_hash' => $projectionHash,
                        ]);

                        foreach ($secDef['tokens'] as $optIdx => $token) {
                            OmrTemplateQuestionOption::query()->create([
                                'template_question_id' => $question->id,
                                'code' => (string) $token,
                                'display_order' => $optIdx + 1,
                                'region_x' => round(0.1 + (0.04 * $optIdx), 6),
                                'region_y' => round(0.05 + (0.85 * ($qNum - 1) / $secDef['count']), 6),
                                'region_w' => 0.03,
                                'region_h' => round(0.80 / $secDef['count'], 6),
                                'projection_manifest_hash' => $projectionHash,
                            ]);
                        }
                    }
                }
            }

            // Activate Answer Sheet Version 1 after descendants are inserted
            if ($sheetVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
                $sheetVersion->forceFill(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED])->save();
                $sheetVersion->forceFill([
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                    'activated_by_user_id' => $actor->id,
                    'activated_at' => now(),
                ])->save();
            }

            // Update Draft 2 to link to this compiled version
            $draft = OmrTemplateAuthoringDraft::query()->find(2);
            if ($draft !== null) {
                $authoring = (array) $draft->authoring_json;
                $authoring['studio']['physicalInventory'] = $physicalInventory;
                $draft->forceFill([
                    'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
                    'compiled_version_id' => $sheetVersion->id,
                    'authoring_json' => $authoring,
                ])->save();
            }

            // 4. Create and finalize the real Booklet ICAT-BOOKLET-2026
            $booklet = OmrQuestionBooklet::query()->firstOrCreate(
                ['code' => 'ICAT-BOOKLET-2026'],
                [
                    'institution_id' => $institution->id,
                    'name' => 'ICAT Question Booklet 2026',
                    'is_synthetic' => true,
                ],
            );

            $bookletVersion = OmrQuestionBookletVersion::query()->firstOrCreate(
                ['booklet_id' => $booklet->id, 'version' => 1],
                [
                    'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                    'allow_multiple_sets' => false,
                    'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
                    'lock_version' => 1,
                    'content_hash' => hash('sha256', 'icat-2026-content'),
                    'answer_key_hash' => hash('sha256', 'icat-2026-keys'),
                ],
            );

            // Populate Booklet Sections, Items, and Set A
            if ($bookletVersion->sections()->doesntExist()) {
                $bookletItems = [];
                $bPos = 1;

                foreach (self::SECTIONS as $index => $secDef) {
                    $bSectionUid = sprintf('00000000-0000-4000-7000-%012d', $index + 1);
                    $section = OmrBookletSection::query()->create([
                        'booklet_version_id' => $bookletVersion->id,
                        'booklet_section_uid' => $bSectionUid,
                        'name' => $secDef['name'],
                        'display_order' => $index + 1,
                        'expected_count' => $secDef['count'],
                        'option_tokens' => $secDef['tokens'],
                    ]);

                    for ($num = 1; $num <= $secDef['count']; $num++) {
                        $bItemUid = sprintf('00000000-0000-4000-6000-%012d', $bPos);
                        $tokens = $secDef['tokens'];
                        $correctToken = $tokens[($num - 1) % count($tokens)];

                        $bItem = OmrBookletItem::query()->create([
                            'booklet_version_id' => $bookletVersion->id,
                            'booklet_section_id' => $section->id,
                            'booklet_item_uid' => $bItemUid,
                            'booklet_section_uid' => $bSectionUid,
                            'authored_position' => $num,
                            'option_tokens' => $tokens,
                            'correct_option_code' => $correctToken,
                            'key_required' => true,
                            'content_hash' => hash('sha256', sprintf('%s Item %d', $secDef['name'], $num)),
                            'encrypted_content' => [
                                'question' => sprintf('%s Item %d', $secDef['name'], $num),
                                'choices' => array_combine($tokens, $tokens),
                            ],
                        ]);
                        $bookletItems[$bPos] = $bItem;
                        $bPos++;
                    }
                }

                $setA = OmrBookletSet::query()->firstOrCreate(
                    ['booklet_version_id' => $bookletVersion->id, 'set_code' => 'A'],
                    [
                        'status' => 'finalized',
                        'order_hash' => hash('sha256', 'order-set-a-190'),
                        'answer_key_hash' => hash('sha256', 'keys-set-a-190'),
                        'finalized_at' => now(),
                    ],
                );
                $order = [];
                $keys = [];
                foreach ($bookletItems as $pos => $bItem) {
                    OmrBookletSetItem::query()->create([
                        'booklet_set_id' => $setA->id,
                        'booklet_version_id' => $bookletVersion->id,
                        'booklet_item_id' => $bItem->id,
                        'booklet_item_uid' => $bItem->booklet_item_uid,
                        'booklet_section_uid' => $bItem->booklet_section_uid,
                        'area_position' => (int) $bItem->authored_position,
                        'global_position' => (int) $pos,
                        'correct_option_code' => $bItem->correct_option_code,
                    ]);

                    $order[] = [
                        'booklet_item_uid' => $bItem->booklet_item_uid,
                        'booklet_section_uid' => $bItem->booklet_section_uid,
                        'global_position' => (int) $pos,
                    ];
                    $keys[] = [
                        'booklet_item_uid' => $bItem->booklet_item_uid,
                        'key_required' => (bool) $bItem->key_required,
                        'correct_option_code' => $bItem->correct_option_code,
                    ];
                }

                $setOrderHash = hash('sha256', json_encode($order, JSON_THROW_ON_ERROR));
                $setKeyHash = hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR));

                $setA->forceFill([
                    'order_hash' => $setOrderHash,
                    'answer_key_hash' => $setKeyHash,
                ])->save();

                $bookletVersion->forceFill([
                    'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
                    'finalized_by_user_id' => $actor->id,
                    'finalized_at' => now(),
                    'content_hash' => hash('sha256', 'icat-2026-content'),
                    'answer_key_hash' => $setKeyHash,
                ])->save();
            } else {
                $setA = $bookletVersion->sets()->where('set_code', 'A')->firstOrFail();
            }
            // 5. Ensure TK-ICAT-2026 has an assembled and active v1 Test Kit
            $kit = TestKit::query()->where('institution_id', $institution->id)->where('code', 'TK-ICAT-2026')->first();
            if ($kit !== null) {
                $prep = $kit->preparations()->where('state', 'preparing')->first();
                if ($prep !== null && $kit->versions()->count() === 0) {
                    $version = app(AssembleTestKitPreparation::class)->handle($actor, (int) $kit->id, [
                        'preparation_id' => (int) $prep->id,
                        'expected_revision' => (int) $prep->revision,
                        'idempotency_key' => (string) Str::uuid(),
                    ]);
                    $version->forceFill([
                        'status' => TestKitVersion::STATUS_ACTIVE,
                        'activated_at' => now(),
                        'activated_by_user_id' => $actor->id,
                        'revision' => (int) $version->revision + 1,
                    ])->save();
                }
            }
        });
    }
}
